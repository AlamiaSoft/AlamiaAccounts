import type React from "react"
import type { Metadata } from "next"
import { Geist, Geist_Mono } from "next/font/google"
import { Providers } from "./providers"
import "./globals.css"

const _geist = Geist({ subsets: ["latin"] })
const _geistMono = Geist_Mono({ subsets: ["latin"] })

export const metadata: Metadata = {
  title: process.env.NEXT_PUBLIC_APP_NAME || "Alamia Accounts",
  description: "Professional Double-Entry Accounting Suite by Alamia",
  icons: {
    icon: [
      { url: "/alamia-logo.png" },
      { url: "/icon-light-32x32.png", sizes: "32x32" },
      { url: "/icon-dark-32x32.png", sizes: "32x32" },
    ],
    shortcut: "/alamia-logo.png",
    apple: "/apple-icon.png",
  },
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html lang="en">
      <body className={`font-sans antialiased`}>
        <Providers>
          {children}
        </Providers>
      </body>
    </html>
  )
}
