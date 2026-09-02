import { type NextRequest, NextResponse } from "next/server"

// Runtime proxy: forwards all /api/* requests to the internal backend.
// Reads BACKEND_INTERNAL_URL from the live container environment at request time.
// The backend is never exposed publicly - only reachable via Docker internal network.

const BACKEND = (process.env.BACKEND_INTERNAL_URL || "http://localhost:8000").replace(/\/$/, "")

async function handler(req: NextRequest, { params }: { params: Promise<{ proxy: string[] }> }) {
  const { proxy } = await params
  const path = proxy.join("/")
  const search = req.nextUrl.search || ""
  const targetUrl = `${BACKEND}/api/${path}${search}`

  const headers = new Headers()
  req.headers.forEach((value, key) => {
    if (!["host", "connection", "transfer-encoding"].includes(key.toLowerCase())) {
      headers.set(key, value)
    }
  })
  headers.set("Accept", "application/json")

  try {
    const backendResponse = await fetch(targetUrl, {
      method: req.method,
      headers,
      body: ["GET", "HEAD"].includes(req.method) ? undefined : await req.text(),
      redirect: "manual",
    })

    const responseBody = await backendResponse.text()
    const responseHeaders = new Headers()
    backendResponse.headers.forEach((value, key) => {
      if (!["transfer-encoding", "connection"].includes(key.toLowerCase())) {
        responseHeaders.set(key, value)
      }
    })

    return new NextResponse(responseBody, {
      status: backendResponse.status,
      headers: responseHeaders,
    })
  } catch (err: any) {
    console.error(`[proxy] Failed to reach backend at ${targetUrl}:`, err?.message)
    return NextResponse.json(
      { message: "Backend unreachable", error: err?.message },
      { status: 502 }
    )
  }
}

export const GET = handler
export const POST = handler
export const PUT = handler
export const PATCH = handler
export const DELETE = handler
export const OPTIONS = handler