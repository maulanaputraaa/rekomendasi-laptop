"use client"

import type React from "react"

import { Head, Link } from "@inertiajs/react"
import { router } from "@inertiajs/react"
import { Search, Star, StarHalf, ChevronRight, ArrowLeft } from "lucide-react"
import { useState, useEffect } from "react"
import { motion, AnimatePresence } from "framer-motion"

import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import AppLayout from "@/layouts/app-layout"
import type { Laptop } from "@/types"
import {route} from "ziggy-js"

interface Props {
  query: string
  results: (Laptop & { average_rating?: number })[]
}

export default function SearchResult({ query: initialQuery, results }: Props) {
  const [query, setQuery] = useState(initialQuery)

  useEffect(() => {
    setQuery(initialQuery)
  }, [initialQuery])

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    router.visit(`/search?query=${encodeURIComponent(query)}`)
  }

  const renderStars = (rating: number) => {
    const fullStars = Math.floor(rating)
    const halfStar = rating - fullStars >= 0.5
    const emptyStars = 5 - fullStars - (halfStar ? 1 : 0)

    return (
      <div className="flex items-center gap-1">
        {[...Array(fullStars)].map((_, i) => (
          <Star key={`full-${i}`} className="w-4 h-4 fill-[var(--amber-500)] text-[var(--amber-500)]" />
        ))}
        {halfStar && <StarHalf className="w-4 h-4 fill-[var(--amber-500)] text-[var(--amber-500)]" />}
        {[...Array(emptyStars)].map((_, i) => (
          <Star key={`empty-${i}`} className="w-4 h-4 text-muted" />
        ))}
        <span className="ml-1 text-sm font-medium text-muted-foreground">({rating.toFixed(1)})</span>
      </div>
    )
  }

  // Animation variants
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
      },
    },
  }

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { type: "spring", stiffness: 100, damping: 15 },
    },
  }

  return (
    <AppLayout>
      <Head title={`Hasil Pencarian: ${query}`} />

      <div className="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        {/* Search Section */}
        <motion.div
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="mb-12"
        >
          <div className="flex items-center justify-between mb-6">
            <motion.div
              initial={{ opacity: 0, x: -20 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.5, delay: 0.2 }}
            >
              <Button
                variant="ghost"
                onClick={() => router.visit("/dashboard")}
                className="flex items-center text-muted-foreground hover:text-foreground"
              >
                <ArrowLeft className="w-4 h-4 mr-2" />
                Kembali ke Dashboard
              </Button>
            </motion.div>
          </div>

          <form onSubmit={handleSearch} className="max-w-2xl mx-auto flex gap-4">
            <div className="relative w-full">
              <Input
                type="text"
                placeholder="Cari berdasarkan merek, seri, atau spesifikasi..."
                className="w-full rounded-lg border-border bg-background/50 shadow-sm focus:border-[var(--blue-600)] focus:ring-2 focus:ring-[var(--blue-500)/20] pl-4 h-12"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
              />
            </div>
            <motion.div whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}>
              <Button
                type="submit"
                className="bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground shadow-lg transition-all hover:shadow-xl h-12 px-6"
              >
                <Search className="w-5 h-5 mr-2" />
                Cari
              </Button>
            </motion.div>
          </form>
        </motion.div>

        {/* Results Section */}
        <div className="space-y-8">
          <motion.h1
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="text-4xl font-bold text-center text-foreground"
          >
            Hasil Pencarian:{" "}
            <span className="bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] bg-clip-text text-transparent">
              {query}
            </span>
          </motion.h1>

          <AnimatePresence>
            {results.length > 0 ? (
              <motion.div
                variants={containerVariants}
                initial="hidden"
                animate="visible"
                className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
              >
                {results.map((laptop) => (
                  <motion.div
                    key={laptop.id}
                    variants={itemVariants}
                    whileHover={{ y: -8, transition: { duration: 0.3 } }}
                    className="group relative bg-card rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 border border-border overflow-hidden"
                  >
                    <div className="absolute inset-0 bg-gradient-to-br from-[var(--blue-500)/5] to-[var(--violet-500)/5] opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                    <div className="p-6 relative z-10">
                      <div className="flex items-center justify-between mb-4">
                        <span className="inline-block bg-[var(--blue-500)/10] text-[var(--blue-600)] dark:bg-[var(--blue-500)/20] dark:text-[var(--blue-400)] text-sm px-3 py-1 rounded-full font-medium">
                          {laptop.brand?.name}
                        </span>
                      </div>

                      <div className="space-y-2">
                        <h2 className="text-xl font-semibold text-foreground group-hover:text-[var(--blue-600)] transition-colors">
                          {laptop.series} {laptop.model}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                          {laptop.cpu} | RAM {laptop.ram}GB | {laptop.gpu}
                        </p>
                      </div>

                      <div className="mt-4">
                        {laptop.average_rating !== undefined && renderStars(laptop.average_rating)}
                      </div>

                      <div className="mt-6 flex items-center justify-between">
                        <p className="text-2xl font-bold text-[var(--emerald-600)] dark:text-[var(--emerald-500)]">
                          Rp {Number(laptop.price).toLocaleString("id-ID")}
                        </p>

                        <motion.div whileHover={{ x: 5 }} whileTap={{ scale: 0.95 }}>
                          <Link
                            href={route("laptops.show", laptop.id)}
                            className="inline-flex items-center text-[var(--blue-600)] hover:text-[var(--blue-700)] dark:text-[var(--blue-400)] font-medium"
                          >
                            Detail
                            <ChevronRight className="w-4 h-4 ml-1 transition-transform group-hover:translate-x-1" />
                          </Link>
                        </motion.div>
                      </div>
                    </div>
                  </motion.div>
                ))}
              </motion.div>
            ) : (
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: 0.3 }}
                className="text-center py-12"
              >
                <div className="max-w-md mx-auto">
                  <motion.div
                    initial={{ scale: 0.8, opacity: 0 }}
                    animate={{ scale: 1, opacity: 1 }}
                    transition={{ duration: 0.5, delay: 0.5 }}
                    className="mb-6 text-muted-foreground"
                  >
                    <div className="w-20 h-20 mx-auto bg-[var(--blue-500)/10] rounded-full flex items-center justify-center">
                      <Search className="w-10 h-10 text-[var(--blue-600)]" />
                    </div>
                  </motion.div>
                  <h2 className="text-xl font-medium text-foreground">Tidak ada hasil ditemukan</h2>
                  <p className="mt-2 text-muted-foreground">Coba kata kunci lain atau periksa ejaan pencarian Anda</p>

                  <motion.div whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }} className="mt-8">
                    <Button
                      onClick={() => router.visit("/dashboard")}
                      className="bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground"
                    >
                      Kembali ke Dashboard
                    </Button>
                  </motion.div>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </AppLayout>
  )
}
