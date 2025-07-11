import type React from "react"
import { Head, useForm, usePage } from "@inertiajs/react"
import AppLayout from "@/layouts/app-layout"
import { type ChangeEvent, useEffect, useState, useCallback } from "react"
import { Button } from "@/components/ui/button"
import { UploadCloud, FileCheck, Loader2, AlertCircle, CheckCircle2, FileSpreadsheet } from "lucide-react"
import { router } from "@inertiajs/react"
import { cn } from "@/lib/utils"
import toast, { Toaster } from "react-hot-toast"
import { Progress } from "@/components/ui/progress"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import type { Auth } from "@/types"
interface Props {
  auth: Auth
}

const ALLOWED_MIME_TYPES = [
  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  "application/vnd.ms-excel",
]
const MAX_FILE_SIZE = 5 * 1024 * 1024 // 5MB

export default function UploadData({ auth }: Props) {
  const { data, setData, post, processing, errors, reset } = useForm({
    file: null as File | null,
  })
  const [isDragging, setIsDragging] = useState(false)
  const [uploadProgress, setUploadProgress] = useState(0)
  const { flash } = usePage<{ flash?: { success?: string; error?: string } }>().props

  // Handle flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success, {
        duration: 5000,
        icon: "🎉",
      })
    }
    if (flash?.error) {
      toast.error(flash.error, {
        duration: 5000,
        icon: "❌",
      })
    }
  }, [flash])

  useEffect(() => {
    if (auth.user?.role !== "admin") {
      router.visit("/")
      toast.error("Akses Ditolak! Anda tidak memiliki izin untuk mengakses halaman ini.")
    }
  }, [auth.user?.role])

  useEffect(() => {
    if (processing) {
      const interval = setInterval(() => {
        setUploadProgress((prev) => {
          const newProgress = prev + Math.random() * 10
          return newProgress > 90 ? 90 : newProgress
        })
      }, 300)

      return () => {
        clearInterval(interval)
        setUploadProgress(0)
      }
    }
  }, [processing])

  const validateFile = useCallback((file: File) => {
    if (!ALLOWED_MIME_TYPES.includes(file.type)) {
      toast.error("Format file tidak valid. Harus berupa .xlsx")
      return false
    }
    if (file.name.split(".").pop()?.toLowerCase() !== "xlsx") {
      toast.error("Format file tidak valid. Harus berupa .xlsx")
      return false
    }
    if (file.size > MAX_FILE_SIZE) {
      toast.error("Ukuran file terlalu besar. Maksimal 5MB")
      return false
    }
    if (file.size === 0) {
      toast.error("File tidak boleh kosong")
      return false
    }
    return true
  }, [])

  const handleFileChange = useCallback(
    (e: ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0]
      if (file && validateFile(file)) {
        setData("file", file)
        toast.success("File berhasil dipilih dan siap untuk diunggah")
      }
    },
    [validateFile, setData],
  )

  const handleDrag = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    setIsDragging(e.type === "dragover")
  }, [])

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault()
      const file = e.dataTransfer.files?.[0]
      if (file && validateFile(file)) {
        setData("file", file)
        toast.success("File berhasil diunggah dan siap untuk diproses")
      }
      setIsDragging(false)
    },
    [validateFile, setData],
  )

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault()
      if (!data.file) return

      // Use the correct route for the import
      post("/reviews/import", {
        onSuccess: () => {
          reset()
          setUploadProgress(100)
          setTimeout(() => setUploadProgress(0), 1000)
        },
        onError: (errors) => {
          if (errors.file) {
            toast.error(errors.file)
          } else {
            toast.error("Terjadi kesalahan saat mengunggah file")
          }
        },
        preserveScroll: true,
      })
    },
    [data.file, post, reset],
  )

  useEffect(() => {
    console.log("Flash messages:", flash)
  }, [flash])

  return (
    <AppLayout>
      <Head title="Upload Data Responder" />
      {/* Make sure Toaster is at the root level */}
      <Toaster
        position="top-right"
        toastOptions={{
          duration: 3000,
          style: {
            background: "#363636",
            color: "#fff",
            padding: "16px",
            borderRadius: "10px",
          },
          success: {
            style: {
              background: "#10B981",
            },
          },
          error: {
            style: {
              background: "#EF4444",
            },
          },
        }}
      />

      <div className="max-w-4xl mx-auto px-4 py-10">
        <Card className="border-0 shadow-lg overflow-hidden bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-950">
          <CardHeader className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white p-8">
            <CardTitle className="text-3xl font-bold">Upload Data Responder</CardTitle>
            <CardDescription className="text-emerald-50 text-lg mt-2">
              Unggah file Excel (.xlsx) berisi data responder penelitian
            </CardDescription>
          </CardHeader>

          <CardContent className="p-8">
            <form onSubmit={handleSubmit} className="space-y-8">
              <div
                className={cn(
                  "group border-2 border-dashed rounded-xl transition-all",
                  "dark:border-gray-700 hover:border-emerald-500 dark:hover:border-emerald-600",
                  "bg-white dark:bg-gray-800/50",
                  isDragging ? "border-emerald-500 bg-emerald-50/80 dark:bg-emerald-900/20" : "border-gray-200",
                  "p-10",
                )}
                onDragOver={handleDrag}
                onDragLeave={handleDrag}
                onDrop={handleDrop}
              >
                <label className="flex flex-col items-center justify-center space-y-6 cursor-pointer">
                  <div
                    className={cn(
                      "p-5 rounded-full shadow-lg transition-all duration-300",
                      data.file
                        ? "bg-emerald-500 dark:bg-emerald-600"
                        : "bg-gradient-to-br from-emerald-500 to-teal-600",
                    )}
                  >
                    {data.file ? (
                      <FileCheck className="w-10 h-10 text-white" />
                    ) : (
                      <UploadCloud
                        className={cn("w-10 h-10 text-white transition-transform", isDragging && "animate-bounce")}
                      />
                    )}
                  </div>

                  <div className="text-center space-y-3">
                    {data.file ? (
                      <div className="space-y-2">
                        <div className="flex items-center justify-center space-x-2">
                          <FileSpreadsheet className="w-5 h-5 text-emerald-500 dark:text-emerald-400" />
                          <p className="font-medium text-gray-900 dark:text-white">File terpilih:</p>
                        </div>
                        <p className="text-emerald-600 dark:text-emerald-400 font-semibold text-lg">{data.file.name}</p>
                        <p className="text-gray-500 dark:text-gray-400 text-sm">
                          {(data.file.size / 1024 / 1024).toFixed(2)} MB
                        </p>
                      </div>
                    ) : (
                      <>
                        <p className="font-medium text-gray-900 dark:text-white text-lg">
                          Drag & drop file Excel atau{" "}
                          <span className="text-emerald-600 dark:text-emerald-400 underline underline-offset-2">
                            browse
                          </span>
                        </p>
                        <div className="flex items-center justify-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                          <AlertCircle className="w-4 h-4" />
                          <p>Format file: .xlsx (Excel), Maksimal 5MB</p>
                        </div>
                      </>
                    )}
                  </div>

                  <input type="file" accept=".xlsx" onChange={handleFileChange} className="hidden" />
                </label>
              </div>

              {errors.file && (
                <div className="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800 flex items-start space-x-3">
                  <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5" />
                  <p className="text-red-600 dark:text-red-400">{errors.file}</p>
                </div>
              )}

              {processing && (
                <div className="space-y-2">
                  <div className="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                    <span>Uploading...</span>
                    <span>{Math.round(uploadProgress)}%</span>
                  </div>
                  <Progress value={uploadProgress} className="h-2 bg-gray-200 dark:bg-gray-700" />
                </div>
              )}

              <Button
                type="submit"
                disabled={processing || !data.file}
                className={cn(
                  "w-full py-6 text-lg font-semibold transition-all",
                  "bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700",
                  "disabled:opacity-50 disabled:cursor-not-allowed",
                  "shadow-md hover:shadow-lg",
                )}
              >
                {processing ? (
                  <>
                    <Loader2 className="w-5 h-5 mr-2 animate-spin" />
                    Mengunggah...
                  </>
                ) : data.file ? (
                  <>
                    <CheckCircle2 className="w-5 h-5 mr-2" />
                    Upload Sekarang
                  </>
                ) : (
                  <>
                    <UploadCloud className="w-5 h-5 mr-2" />
                    Pilih File Terlebih Dahulu
                  </>
                )}
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  )
}