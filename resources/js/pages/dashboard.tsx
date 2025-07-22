import { Head } from "@inertiajs/react"
import { router } from "@inertiajs/react"
import { Search, Laptop, ChevronRight, Filter, X } from "lucide-react"
import { useState } from "react"
import { FaStar, FaStarHalfAlt, FaRegStar } from "react-icons/fa"
import { motion } from "framer-motion"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Label } from "@/components/ui/label"
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
  const [filters, setFilters] = useState<FilterState>({
    brands: [],
    ram: [],
    usage: [],
    processor: [],
    priceRange: {
      min: 0,
      max: 50000000
    }
  })

  // Data filter options (statis sesuai permintaan)
  const filterOptions = {
    brands: ["ASUS", "Acer", "HP", "Lenovo", "MSI"],
    ram: ["4GB", "8GB", "16GB", "32GB", "64GB"],
    usage: ["Gaming", "Kantor", "Desain Grafis", "Programming", "Multimedia", "Sekolah"],
    processor: ["Intel", "AMD"]
  }

  const handleSearch = () => {
    if (query.trim() || hasActiveFilters()) {
      // Gabungkan query pencarian dengan filter yang aktif
      const searchParams = new URLSearchParams()

      if (query.trim()) {
        searchParams.append('query', query)
      }

      // Tambahkan filter ke query string
      if (filters.brands.length > 0) {
        searchParams.append('brands', filters.brands.join(','))
      }
      if (filters.ram.length > 0) {
        searchParams.append('ram', filters.ram.join(','))
      }
      if (filters.usage.length > 0) {
        searchParams.append('usage', filters.usage.join(','))
      }
      if (filters.processor.length > 0) {
        searchParams.append('processor', filters.processor.join(','))
      }
      if (filters.priceRange.min > 0 || filters.priceRange.max < 50000000) {
        searchParams.append('price_min', filters.priceRange.min.toString())
        searchParams.append('price_max', filters.priceRange.max.toString())
      }

      router.visit(`/search?${searchParams.toString()}`)
    }
  }

  const hasActiveFilters = () => {
    return filters.brands.length > 0 ||
      filters.ram.length > 0 ||
      filters.usage.length > 0 ||
      filters.processor.length > 0 ||
      filters.priceRange.min > 0 ||
      filters.priceRange.max < 50000000
  }

  const handleFilterChange = (category: keyof FilterState, value: string | { min: number; max: number }) => {
    if (category === 'priceRange') {
      setFilters(prev => ({
        ...prev,
        priceRange: value as { min: number; max: number }
      }))
    } else {
      setFilters(prev => ({
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
    setFilters({
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

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0
    }).format(price)
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
            className="mb-8 text-center"
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
                            checked={filters.brands.includes(brand)}
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
                            checked={filters.ram.includes(ram)}
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
                            checked={filters.usage.includes(usage)}
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
                            checked={filters.processor.includes(processor)}
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
                            value={filters.priceRange.min === 0 ? '' : filters.priceRange.min}
                            onChange={(e) => {
                              const value = parseInt(e.target.value) || 0
                              handleFilterChange('priceRange', {
                                ...filters.priceRange,
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
                            value={filters.priceRange.max === 50000000 ? '' : filters.priceRange.max}
                            onChange={(e) => {
                              const value = parseInt(e.target.value) || 50000000
                              handleFilterChange('priceRange', {
                                ...filters.priceRange,
                                max: value
                              })
                            }}
                            className="h-8 text-xs"
                          />
                        </div>
                      </div>
                      <div className="text-xs text-muted-foreground">
                        {filters.priceRange.min === 0 && filters.priceRange.max === 50000000
                          ? "Semua harga"
                          : `${formatPrice(filters.priceRange.min)} - ${formatPrice(filters.priceRange.max)}`
                        }
                      </div>
                    </div>
                  </div>

                  {/* Apply Filters Button */}
                  <Button
                    onClick={handleSearch}
                    className="w-full bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground"
                    disabled={!query.trim() && !hasActiveFilters()}
                  >
                    <Search className="w-4 h-4 mr-2" />
                    Terapkan Filter
                  </Button>
                </div>
              </div>
            </motion.div>

            {/* Laptop Grid */}
            <div className="flex-1">
              {Array.isArray(laptops) && laptops.length > 0 ? (
                <motion.div
                  variants={containerVariants}
                  initial="hidden"
                  animate="visible"
                  className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
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
                          {laptop.series && <p className="text-sm text-muted-foreground">{laptop.series}</p>}
                          {laptop.model && <p className="text-sm text-muted-foreground">{laptop.model}</p>}
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
        </div>
      </div>
    </AppLayout>
  )
}
