"use client"

import type { PropsWithChildren } from "react"

interface AuthLayoutProps extends PropsWithChildren {
  title: string
  description: string
}

export default function AuthLayout({ children,}: AuthLayoutProps) {
  return (
    <div className="min-h-screen flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-background">
      <div className="w-full max-w-md space-y-8">
        {/* Logo dan judul dipindahkan ke dalam komponen Login */}
        {children}
      </div>
    </div>
  )
}
