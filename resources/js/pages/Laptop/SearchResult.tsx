import type React from "react"
import { Head } from "@inertiajs/react"
import { router } from "@inertiajs/react"
import { Search, Star, StarHalf, ChevronRight, ArrowLeft, Filter, X } from "lucide-react"
import { useState, useEffect } from "react"
import { motion, AnimatePresence } from "framer-motion"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Label } from "@/components/ui/label"
import AppLayout from "@/layouts/app-layout"
import type { Laptop } from "@/types"

interface FilterState {
  brands: string[]
  ram: string[]
  usage: string[]
  processor: string[]
  priceRange: {
    min: number
    max: number
  }
}
interface Props {
  query: string
  results: (Laptop & {
    average_rating?: number
    price_range?: {
      min: number
      max: number
    }
  })[]
  filters?: {
    brands?: string
    ram?: string
    usage?: string
    processor?: string
    price_min?: number
    price_max?: number
  }
}

export default function SearchResult({ query: initialQuery, results, filters }: Props) {
  const [query, setQuery] = useState(initialQuery)
  const [currentFilters, setCurrentFilters] = useState<FilterState>({
    brands: [],
    ram: [],
    usage: [],
    processor: [],
    priceRange: {
      min: 0,
      max: 50000000
    }
  })

  // Data filter options (sama dengan dashboard)
  const filterOptions = {
    brands: ["ASUS", "Acer", "HP", "Lenovo", "MSI"],
    ram: ["4GB", "8GB", "16GB", "32GB", "64GB"],
    usage: ["Gaming", "Kantor", "Desain Grafis", "Programming", "Multimedia", "Sekolah"],
    processor: ["Intel", "AMD"]
  }

  useEffect(() => {
    setQuery(initialQuery)

    // Set filter dari props jika ada
    if (filters) {
      setCurrentFilters({
        brands: filters.brands ? filters.brands.split(',') : [],
        ram: filters.ram ? filters.ram.split(',') : [],
        usage: filters.usage ? filters.usage.split(',') : [],
        processor: filters.processor ? filters.processor.split(',') : [],
        priceRange: {
          min: filters.price_min || 0,
          max: filters.price_max || 50000000
        }
      })
    }
  }, [initialQuery, filters])

  const hasActiveFilters = () => {
    return currentFilters.brands.length > 0 ||
      currentFilters.ram.length > 0 ||
      currentFilters.usage.length > 0 ||
      currentFilters.processor.length > 0 ||
      currentFilters.priceRange.min > 0 ||
      currentFilters.priceRange.max < 50000000
  }

  const handleFilterChange = (category: keyof FilterState, value: string | { min: number; max: number }) => {
    if (category === 'priceRange') {
      setCurrentFilters(prev => ({
        ...prev,
        priceRange: value as { min: number; max: number }
      }))
    } else {
      setCurrentFilters(prev => ({
        ...prev,
        [category]: Array.isArray(prev[category])
          ? (prev[category] as string[]).includes(value as string)
            ? (prev[category] as string[]).filter(item => item !== value)
            : [...(prev[category] as string[]), value as string]
          : [value as string]
      }))
    }
  }

  const clearFilters = () => {
    setCurrentFilters({
      brands: [],
      ram: [],
      usage: [],
      processor: [],
      priceRange: {
        min: 0,
        max: 50000000
      }
    })
  }

  const handleNewSearch = () => {
    if (query.trim() || hasActiveFilters()) {
      const searchParams = new URLSearchParams()

      if (query.trim()) {
        searchParams.append('query', query)
      }

      if (currentFilters.brands.length > 0) {
        searchParams.append('brands', currentFilters.brands.join(','))
      }
      if (currentFilters.ram.length > 0) {
        searchParams.append('ram', currentFilters.ram.join(','))
      }
      if (currentFilters.usage.length > 0) {
        searchParams.append('usage', currentFilters.usage.join(','))
      }
      if (currentFilters.processor.length > 0) {
        searchParams.append('processor', currentFilters.processor.join(','))
      }
      if (currentFilters.priceRange.min > 0 || currentFilters.priceRange.max < 50000000) {
        searchParams.append('price_min', currentFilters.priceRange.min.toString())
        searchParams.append('price_max', currentFilters.priceRange.max.toString())
      }

      router.visit(`/search?${searchParams.toString()}`)
    }
  }

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0
    }).format(price)
  }

  // Helper function untuk menampilkan informasi harga
  const formatPriceInfo = (laptop: Laptop & {
    average_rating?: number
    price_range?: {
      min: number
      max: number
    }
  }) => {
    // Jika laptop memiliki price_range, tampilkan rentang harga
    if (laptop.price_range && laptop.price_range.min !== laptop.price_range.max) {
      return (
        <div className="space-y-1">
          <p className="text-2xl font-bold text-[var(--emerald-600)] dark:text-[var(--emerald-500)]">
            {formatPrice(laptop.price)}
          </p>
          <p className="text-xs text-muted-foreground">
            Rata-rata dari {formatPrice(laptop.price_range.min)} - {formatPrice(laptop.price_range.max)}
          </p>
        </div>
      )
    }

    // Jika hanya ada satu harga
    return (
      <p className="text-2xl font-bold text-[var(--emerald-600)] dark:text-[var(--emerald-500)]">
        {formatPrice(laptop.price)}
      </p>
    )
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

  const getDisplayTitle = () => {
    if (!initialQuery || initialQuery.trim() === "") {
      return "Hasil Pencarian"
    }
    return (
      <>
        Hasil Pencarian:{" "}
        <span className="bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] bg-clip-text text-transparent">
          {initialQuery}
        </span>
      </>
    )
  }

  return (
    <AppLayout>
      <Head title={initialQuery ? `Hasil Pencarian: ${initialQuery}` : "Hasil Pencarian"} />

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
              className="min-w-0"
            >
              <Button
                variant="ghost"
                onClick={() => router.visit("/dashboard")}
                className="flex items-center text-muted-foreground hover:text-foreground whitespace-nowrap"
              >
                <ArrowLeft className="w-4 h-4 mr-2 flex-shrink-0" />
                <span className="flex-shrink-0">Kembali ke Dashboard</span>
              </Button>
            </motion.div>
          </div>

          <form onSubmit={(e) => {
            e.preventDefault()
            handleNewSearch()
          }} className="max-w-2xl mx-auto flex gap-4">
            <div className="relative flex-1 min-w-0">
              <Input
                type="text"
                placeholder="Cari berdasarkan merek, seri, atau spesifikasi..."
                className="w-full min-w-[300px] rounded-lg border-border bg-background/50 shadow-sm focus:border-[var(--blue-600)] focus:ring-2 focus:ring-[var(--blue-500)/20] pl-4 h-12"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
              />
            </div>
            <motion.div whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }} className="flex-shrink-0">
              <Button
                type="submit"
                className="bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground shadow-lg transition-all hover:shadow-xl h-12 px-6 whitespace-nowrap"
              >
                <Search className="w-5 h-5 mr-2 flex-shrink-0" />
                <span className="flex-shrink-0">Cari</span>
              </Button>
            </motion.div>
          </form>
        </motion.div>

        {/* Results Section with Sidebar */}
        <div className="space-y-8">
          <motion.h1
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="text-4xl font-bold text-center text-foreground min-h-[3rem] flex items-center justify-center"
          >
            {getDisplayTitle()}
          </motion.h1>

          {/* Main Content with Sidebar */}
          <div className="flex gap-6">
            {/* Filter Sidebar */}
            <motion.div
              initial={{ opacity: 0, x: -20 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.5, delay: 0.2 }}
              className="w-80 flex-shrink-0"
            >
              <div className="bg-card rounded-xl shadow-lg border border-border p-6 sticky top-6">
                <div className="flex items-center justify-between mb-6">
                  <h2 className="text-xl font-semibold flex items-center gap-2">
                    <Filter className="w-5 h-5" />
                    Filter Pencarian
                  </h2>
                  {hasActiveFilters() && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={clearFilters}
                      className="text-xs"
                    >
                      <X className="w-3 h-3 mr-1" />
                      Reset
                    </Button>
                  )}
                </div>

                <div className="space-y-6">
                  {/* Brand Filter */}
                  <div>
                    <h3 className="font-medium mb-3 text-sm text-muted-foreground uppercase tracking-wide">Brand</h3>
                    <div className="space-y-3">
                      {filterOptions.brands.map((brand) => (
                        <div key={brand} className="flex items-center space-x-2">
                          <Checkbox
                            id={`brand-${brand}`}
                            checked={currentFilters.brands.includes(brand)}
                            onCheckedChange={() => handleFilterChange('brands', brand)}
                          />
                          <Label
                            htmlFor={`brand-${brand}`}
                            className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                          >
                            {brand}
                          </Label>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* RAM Filter */}
                  <div>
                    <h3 className="font-medium mb-3 text-sm text-muted-foreground uppercase tracking-wide">RAM</h3>
                    <div className="space-y-3">
                      {filterOptions.ram.map((ram) => (
                        <div key={ram} className="flex items-center space-x-2">
                          <Checkbox
                            id={`ram-${ram}`}
                            checked={currentFilters.ram.includes(ram)}
                            onCheckedChange={() => handleFilterChange('ram', ram)}
                          />
                          <Label
                            htmlFor={`ram-${ram}`}
                            className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                          >
                            {ram}
                          </Label>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Usage Filter */}
                  <div>
                    <h3 className="font-medium mb-3 text-sm text-muted-foreground uppercase tracking-wide">Kebutuhan</h3>
                    <div className="space-y-3">
                      {filterOptions.usage.map((usage) => (
                        <div key={usage} className="flex items-center space-x-2">
                          <Checkbox
                            id={`usage-${usage}`}
                            checked={currentFilters.usage.includes(usage)}
                            onCheckedChange={() => handleFilterChange('usage', usage)}
                          />
                          <Label
                            htmlFor={`usage-${usage}`}
                            className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                          >
                            {usage}
                          </Label>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Processor Filter */}
                  <div>
                    <h3 className="font-medium mb-3 text-sm text-muted-foreground uppercase tracking-wide">Prosesor</h3>
                    <div className="space-y-3">
                      {filterOptions.processor.map((processor) => (
                        <div key={processor} className="flex items-center space-x-2">
                          <Checkbox
                            id={`processor-${processor}`}
                            checked={currentFilters.processor.includes(processor)}
                            onCheckedChange={() => handleFilterChange('processor', processor)}
                          />
                          <Label
                            htmlFor={`processor-${processor}`}
                            className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                          >
                            {processor}
                          </Label>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Price Range Filter */}
                  <div>
                    <h3 className="font-medium mb-3 text-sm text-muted-foreground uppercase tracking-wide">Rentang Harga</h3>
                    <div className="space-y-4">
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <Label htmlFor="price-min" className="text-xs text-muted-foreground">Minimum</Label>
                          <Input
                            id="price-min"
                            type="number"
                            placeholder="0"
                            value={currentFilters.priceRange.min === 0 ? '' : currentFilters.priceRange.min}
                            onChange={(e) => {
                              const value = parseInt(e.target.value) || 0
                              handleFilterChange('priceRange', {
                                ...currentFilters.priceRange,
                                min: value
                              })
                            }}
                            className="h-8 text-xs"
                          />
                        </div>
                        <div>
                          <Label htmlFor="price-max" className="text-xs text-muted-foreground">Maksimum</Label>
                          <Input
                            id="price-max"
                            type="number"
                            placeholder="50000000"
                            value={currentFilters.priceRange.max === 50000000 ? '' : currentFilters.priceRange.max}
                            onChange={(e) => {
                              const value = parseInt(e.target.value) || 50000000
                              handleFilterChange('priceRange', {
                                ...currentFilters.priceRange,
                                max: value
                              })
                            }}
                            className="h-8 text-xs"
                          />
                        </div>
                      </div>
                      <div className="text-xs text-muted-foreground">
                        {currentFilters.priceRange.min === 0 && currentFilters.priceRange.max === 50000000
                          ? "Semua harga"
                          : `${formatPrice(currentFilters.priceRange.min)} - ${formatPrice(currentFilters.priceRange.max)}`
                        }
                      </div>
                    </div>
                  </div>

                  {/* Apply Filters Button */}
                  <Button
                    onClick={handleNewSearch}
                    className="w-full bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground"
                    disabled={!query.trim() && !hasActiveFilters()}
                  >
                    <Search className="w-4 h-4 mr-2" />
                    Terapkan Filter
                  </Button>
                </div>
              </div>
            </motion.div>

            {/* Results Grid */}
            <div className="flex-1">
              <AnimatePresence>
                {results.length > 0 ? (
                  <motion.div
                    variants={containerVariants}
                    initial="hidden"
                    animate="visible"
                    className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
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
                              {laptop.brand?.name || 'Unknown'}
                            </span>
                          </div>

                          <div className="space-y-2">
                            <h3 className="text-xl font-semibold text-foreground group-hover:text-[var(--blue-600)] transition-colors line-clamp-2">
                              {`${laptop.series} ${laptop.model}`}
                            </h3>
                          </div>

                          <div className="mt-4">
                            {formatPriceInfo(laptop)}
                          </div>

                          <div className="mt-4 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                              {typeof laptop.average_rating === "number" ? (
                                renderStars(laptop.average_rating)
                              ) : (
                                <span className="text-sm text-muted-foreground">{laptop.average_rating || 'Belum ada rating'}</span>
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
                          className="bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground whitespace-nowrap"
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
        </div>
      </div>
    </AppLayout>
  )
}
