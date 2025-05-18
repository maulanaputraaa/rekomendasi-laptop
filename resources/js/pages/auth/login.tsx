"use client"

import { Head, useForm } from "@inertiajs/react"
import { LoaderCircle, Mail, Lock } from "lucide-react"
import type { FormEventHandler } from "react"
import { motion } from "framer-motion"

import InputError from "@/components/input-error"
import TextLink from "@/components/text-link"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import AuthLayout from "@/layouts/auth-layout"
import {route} from "ziggy-js"

type LoginForm = {
  email: string
  password: string
  remember: boolean
}

interface LoginProps {
  status?: string
  canResetPassword: boolean
}

export default function Login({ status, canResetPassword }: LoginProps) {
  const { data, setData, post, processing, errors, reset } = useForm<Required<LoginForm>>({
    email: "",
    password: "",
    remember: false,
  })

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route("login"), {
      onFinish: () => reset("password"),
    })
  }

  return (
    <AuthLayout title="Selamat Datang Kembali" description="">
      <Head title="Masuk" />

      {/* Animated background elements */}
      <div className="fixed inset-0 overflow-hidden -z-10">
        <div className="absolute top-0 right-0 w-full h-full bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-[var(--blue-500)/10] via-background to-background opacity-70 dark:from-[var(--blue-600)/30] dark:via-background dark:to-background"></div>

        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 0.2, scale: 1 }}
          transition={{ duration: 3, repeat: Number.POSITIVE_INFINITY, repeatType: "mirror" }}
          className="absolute top-20 left-[10%] w-72 h-72 rounded-full bg-gradient-to-r from-[var(--blue-500)] to-[var(--violet-500)] blur-3xl opacity-20 dark:opacity-10"
        />

        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 0.15, scale: 1 }}
          transition={{ duration: 4, delay: 1, repeat: Number.POSITIVE_INFINITY, repeatType: "mirror" }}
          className="absolute bottom-32 right-[15%] w-80 h-80 rounded-full bg-gradient-to-r from-[var(--emerald-500)] to-[var(--teal-500)] blur-3xl opacity-20 dark:opacity-10"
        />
      </div>

      <div className="text-center mb-6">
        <h1 className="text-2xl md:text-3xl font-bold text-foreground">Selamat Datang Kembali</h1>
        <p className="text-muted-foreground mt-2">Masuk untuk mengakses rekomendasi laptop personal Anda</p>
      </div>

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="relative rounded-2xl bg-card border border-border p-8 shadow-xl sm:p-10 max-w-md w-full mx-auto"
      >
        {status && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="mb-6 rounded-lg bg-[var(--emerald-500)/10] p-4 text-center text-sm font-medium text-[var(--emerald-600)] dark:bg-[var(--emerald-500)/20] dark:text-[var(--emerald-500)]"
          >
            {status}
          </motion.div>
        )}

        <form className="flex flex-col gap-6" onSubmit={submit}>
          <div className="grid gap-6">
            <div className="space-y-4">
              <div className="grid gap-2">
                <Label htmlFor="email" className="text-foreground font-medium flex items-center gap-2">
                  <Mail className="w-4 h-4 text-[var(--blue-600)]" />
                  Alamat Email
                </Label>
                <div className="relative">
                  <Input
                    id="email"
                    type="email"
                    required
                    autoFocus
                    tabIndex={1}
                    autoComplete="email"
                    value={data.email}
                    onChange={(e) => setData("email", e.target.value)}
                    placeholder="email@contoh.com"
                    className="rounded-lg border-border bg-background/50 transition-all focus:border-[var(--blue-600)] focus:ring-2 focus:ring-[var(--blue-500)/20] pl-4"
                  />
                </div>
                <InputError message={errors.email} />
              </div>

              <div className="grid gap-2">
                <div className="flex items-center">
                  <Label htmlFor="password" className="text-foreground font-medium flex items-center gap-2">
                    <Lock className="w-4 h-4 text-[var(--blue-600)]" />
                    Password
                  </Label>
                  {canResetPassword && (
                    <TextLink
                      href={route("password.request")}
                      className="ml-auto text-sm font-medium text-[var(--blue-600)] hover:text-[var(--blue-700)] transition-colors"
                      tabIndex={5}
                    >
                      Lupa Password?
                    </TextLink>
                  )}
                </div>
                <div className="relative">
                  <Input
                    id="password"
                    type="password"
                    required
                    tabIndex={2}
                    autoComplete="current-password"
                    value={data.password}
                    onChange={(e) => setData("password", e.target.value)}
                    placeholder="••••••••"
                    className="rounded-lg border-border bg-background/50 transition-all focus:border-[var(--blue-600)] focus:ring-2 focus:ring-[var(--blue-500)/20] pl-4"
                  />
                </div>
                <InputError message={errors.password} />
              </div>

              <div className="flex items-center gap-3">
                <Checkbox
                  id="remember"
                  name="remember"
                  checked={data.remember}
                  onClick={() => setData("remember", !data.remember)}
                  tabIndex={3}
                  className="border-border text-[var(--blue-600)] focus:ring-[var(--blue-500)]"
                />
                <Label htmlFor="remember" className="text-muted-foreground">
                  Ingat Saya
                </Label>
              </div>
            </div>

            <motion.div whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }}>
              <Button
                type="submit"
                className="w-full h-12 rounded-lg bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-lg font-semibold text-primary-foreground shadow-lg transition-all hover:shadow-xl"
                tabIndex={4}
                disabled={processing}
              >
                {processing ? (
                  <LoaderCircle className="h-5 w-5 animate-spin text-primary-foreground" />
                ) : (
                  "Masuk ke Akun"
                )}
              </Button>
            </motion.div>
          </div>

          <div className="text-center text-sm text-muted-foreground">
            Belum punya akun?{" "}
            <TextLink
              href={route("register")}
              className="font-semibold text-[var(--blue-600)] hover:text-[var(--blue-700)] transition-colors"
              tabIndex={5}
            >
              Daftar Sekarang
            </TextLink>
          </div>
        </form>
      </motion.div>
    </AuthLayout>
  )
}
