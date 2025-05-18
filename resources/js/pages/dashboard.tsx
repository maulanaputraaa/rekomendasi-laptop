import { Head } from "@inertiajs/react"
import { router } from "@inertiajs/react"
import { Search, Laptop, ChevronRight } from "lucide-react"
import { useState } from "react"
import { FaStar, FaStarHalfAlt, FaRegStar } from "react-icons/fa"
import { motion } from "framer-motion"

import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import AppLayout from "@/layouts/app-layout"
import type { BreadcrumbItem } from "@/types"

interface LaptopType {
  id: number
  name: string
  brand: string
  series: string
  model: string
  price: string
  average_rating: number | string
}

interface Props {
  laptops: LaptopType[]
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "Dashboard",
    href: "/dashboard",
  },
]

function renderRatingStars(rating: number) {
  const fullStars = Math.floor(rating)
  const halfStars = rating % 1 >= 0.5 ? 1 : 0
  const emptyStars = 5 - fullStars - halfStars

  return (
    <div className="flex items-center gap-1">
      {[...Array(fullStars)].map((_, i) => (
        <FaStar key={`full-${i}`} className="text-[var(--amber-500)] w-4 h-4" />
      ))}
      {[...Array(halfStars)].map((_, i) => (
        <FaStarHalfAlt key={`half-${i}`} className="text-[var(--amber-500)] w-4 h-4" />
      ))}
      {[...Array(emptyStars)].map((_, i) => (
        <FaRegStar key={`empty-${i}`} className="text-[var(--amber-500)] w-4 h-4" />
      ))}
    </div>
  )
}

export default function Dashboard({ laptops }: Props) {
  const [query, setQuery] = useState("")

  const handleSearch = () => {
    if (query.trim()) {
      router.visit(`/search?query=${encodeURIComponent(query)}`)
    }
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
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard" />

      <div className="px-4 pt-6 pb-8">
        <div className="mx-auto max-w-7xl">
          {/* Search Section */}
          <motion.div
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="mb-12 text-center"
          >
            <h1 className="text-4xl font-bold mb-4 bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] bg-clip-text text-transparent">
              Temukan Laptop Impian Anda
            </h1>
            <form
              onSubmit={(e) => {
                e.preventDefault()
                handleSearch()
              }}
              className="max-w-2xl mx-auto flex gap-4"
            >
              <div className="relative w-full">
                <Input
                  type="text"
                  placeholder="Cari berdasarkan merek, seri, atau fitur..."
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

          {/* Laptop Grid */}
          {Array.isArray(laptops) && laptops.length > 0 ? (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
            >
              {laptops.map((laptop) => (
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
                        {laptop.brand}
                      </span>
                    </div>

                    <div className="space-y-2">
                      <h3 className="text-xl font-semibold text-foreground group-hover:text-[var(--blue-600)] transition-colors">
                        {laptop.name}
                      </h3>
                      {laptop.series && <p className="text-sm text-muted-foreground">Seri: {laptop.series}</p>}
                      {laptop.model && <p className="text-sm text-muted-foreground">Model: {laptop.model}</p>}
                    </div>

                    <div className="mt-4">
                      <p className="text-2xl font-bold text-[var(--emerald-600)] dark:text-[var(--emerald-500)]">
                        {laptop.price}
                      </p>
                    </div>

                    <div className="mt-4 flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        {typeof laptop.average_rating === "number" ? (
                          <>
                            {renderRatingStars(laptop.average_rating)}
                            <span className="text-sm text-muted-foreground">({laptop.average_rating.toFixed(1)})</span>
                          </>
                        ) : (
                          <span className="text-sm text-muted-foreground">{laptop.average_rating}</span>
                        )}
                      </div>
                    </div>

                    <motion.div whileHover={{ scale: 1.03 }} whileTap={{ scale: 0.97 }} className="mt-6">
                      <Button
                        className="w-full bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground hover:shadow-lg group"
                        onClick={() => router.visit(`/laptops/${laptop.id}`)}
                      >
                        Lihat Detail
                        <ChevronRight className="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" />
                      </Button>
                    </motion.div>
                  </div>
                </motion.div>
              ))}
            </motion.div>
          ) : (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ duration: 0.5, delay: 0.2 }}
              className="text-center py-12"
            >
              <div className="w-20 h-20 mx-auto bg-[var(--blue-500)/10] rounded-full flex items-center justify-center mb-4">
                <Laptop className="w-10 h-10 text-[var(--blue-600)]" />
              </div>
              <p className="text-muted-foreground text-lg">Belum ada data laptop tersedia.</p>
            </motion.div>
          )}
        </div>
      </div>
    </AppLayout>
  )
}
