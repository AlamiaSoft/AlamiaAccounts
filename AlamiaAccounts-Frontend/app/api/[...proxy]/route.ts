import { type NextRequest, NextResponse } from "next/server"

// Runtime proxy: forwards all /api/* requests to the internal backend.
// Supports dual-stack fallback (localhost, 127.0.0.1, [::1]) to ensure rock-solid connectivity.

const PRIMARY_BACKEND = (process.env.BACKEND_INTERNAL_URL || "http://backend:8000").replace(/\/$/, "")
const FALLBACK_HOSTS = [
  "http://backend:8000",
  "http://127.0.0.1:8000",
  "http://localhost:8000",
  "http://host.docker.internal:8000",
  "http://[::1]:8000"
]

async function forwardRequest(req: NextRequest, targetUrl: string, bodyText?: string) {
  const headers = new Headers()
  req.headers.forEach((value, key) => {
    if (!["host", "connection", "transfer-encoding"].includes(key.toLowerCase())) {
      headers.set(key, value)
    }
  })
  headers.set("Accept", "application/json")

  return await fetch(targetUrl, {
    method: req.method,
    headers,
    body: ["GET", "HEAD"].includes(req.method) ? undefined : bodyText,
    redirect: "manual",
  })
}

async function handler(req: NextRequest, { params }: { params: Promise<{ proxy: string[] }> }) {
  const { proxy } = await params
  const path = proxy.join("/")
  const search = req.nextUrl.search || ""
  const bodyText = ["GET", "HEAD"].includes(req.method) ? undefined : await req.text()

  const candidateUrls = [
    `${PRIMARY_BACKEND}/api/${path}${search}`,
    ...FALLBACK_HOSTS.map(h => `${h}/api/${path}${search}`)
  ]
  const uniqueUrls = Array.from(new Set(candidateUrls))

  let lastError: any = null
  let successfulResponse: Response | null = null

  for (const targetUrl of uniqueUrls) {
    try {
      successfulResponse = await forwardRequest(req, targetUrl, bodyText)
      break;
    } catch (err: any) {
      lastError = err
    }
  }

  if (successfulResponse) {
    const responseBody = await successfulResponse.text()
    const responseHeaders = new Headers()
    successfulResponse.headers.forEach((value, key) => {
      if (!["transfer-encoding", "connection"].includes(key.toLowerCase())) {
        responseHeaders.set(key, value)
      }
    })

    return new NextResponse(responseBody, {
      status: successfulResponse.status,
      headers: responseHeaders,
    })
  }

  const errorDetails = lastError?.cause ? (lastError.cause.message || JSON.stringify(lastError.cause)) : lastError?.message
  console.error(`[proxy] Failed to reach backend at ${uniqueUrls.join(', ')}:`, errorDetails)
  return NextResponse.json(
    { message: "Backend unreachable", error: lastError?.message, details: errorDetails },
    { status: 502 }
  )
}

export const GET = handler
export const POST = handler
export const PUT = handler
export const PATCH = handler
export const DELETE = handler
export const OPTIONS = handler