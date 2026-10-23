/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  basePath: '/alamia-accounts',
  typescript: {
    ignoreBuildErrors: true,
  },
  images: {
    unoptimized: true,
  },
  async redirects() {
    return [
      {
        source: '/',
        destination: '/alamia-accounts',
        basePath: false,
        permanent: false,
      },
    ]
  },
  // Proxy /api/* → internal backend container (Docker network).
  // The browser never needs direct access to the backend host/port.
  async rewrites() {
    const backendUrl = process.env.BACKEND_INTERNAL_URL || 'http://localhost:8000'
    return [
      {
        source: '/api/:path*',
        destination: `${backendUrl}/api/:path*`,
        basePath: false,
      },
      {
        source: '/alamia-accounts/api/:path*',
        destination: `${backendUrl}/api/:path*`,
        basePath: false,
      },
    ]
  },
}

export default nextConfig
