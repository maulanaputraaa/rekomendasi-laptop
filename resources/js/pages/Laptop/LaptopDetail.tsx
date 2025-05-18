import type React from "react"
import { Head, Link } from "@inertiajs/react"
import AppLayout from "@/layouts/app-layout"
import type { Laptop, Review } from "@/types"
import { Button } from "@/components/ui/button"
import { ArrowLeft, Star, StarHalf, Cpu, MemoryStick, HardDrive, Monitor, Calendar, MessageSquare } from "lucide-react"
import { motion, AnimatePresence } from "framer-motion"

interface Props {
  laptop: Laptop & {
    reviews: Review[]
    average_rating: number
  }
}

export default function LaptopDetail({ laptop }: Props) {
  const renderStars = (rating: number) => {
    const fullStars = Math.floor(rating)
    const halfStar = rating - fullStars >= 0.5
    const emptyStars = 5 - fullStars - (halfStar ? 1 : 0)

    return (
      <div className="flex items-center gap-1">
        {[...Array(fullStars)].map((_, i) => (
          <Star key={`full-${i}`} className="w-5 h-5 fill-[var(--amber-500)] text-[var(--amber-500)]" />
        ))}
        {halfStar && <StarHalf className="w-5 h-5 fill-[var(--amber-500)] text-[var(--amber-500)]" />}
        {[...Array(emptyStars)].map((_, i) => (
          <Star key={`empty-${i}`} className="w-5 h-5 text-muted" />
        ))}
        <span className="ml-2 text-sm font-medium text-muted-foreground">({rating.toFixed(1)})</span>
      </div>
    )
  }

  const SpecItem = ({
    icon: Icon,
    title,
    value,
    color,
  }: {
    icon: React.ComponentType<{ className?: string }>
    title: string
    value: string
    color: string
  }) => (
    <motion.div
      whileHover={{ y: -5 }}
      className="flex items-start gap-4 p-4 bg-card/50 rounded-xl border border-border shadow-sm hover:shadow-md transition-all"
    >
      <div className={`p-2 ${color} rounded-lg`}>
        <Icon className="w-6 h-6 text-white" />
      </div>
      <div>
        <h3 className="text-sm font-medium text-muted-foreground">{title}</h3>
        <p className="mt-1 text-lg font-semibold text-foreground">{value}</p>
      </div>
    </motion.div>
  )

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
      <Head title={`Detail ${laptop.brand?.name} ${laptop.series} ${laptop.model}`} />

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

      <div className="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="mb-8"
        >
          <Button
            asChild
            variant="ghost"
            className="text-muted-foreground hover:bg-secondary hover:text-foreground transition-all duration-200"
          >
            <Link href={route("dashboard")} className="flex items-center gap-2">
              <ArrowLeft className="w-5 h-5" />
              <span>Kembali ke Pencarian</span>
            </Link>
          </Button>
        </motion.div>

        {/* Main Content */}
        <div className="space-y-12">
          {/* Product Header */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="bg-card rounded-2xl shadow-lg p-8 border border-border"
          >
            <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-8">
              <div className="flex-1">
                <motion.h1
                  initial={{ opacity: 0, y: -10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.5, delay: 0.2 }}
                  className="text-4xl font-extrabold bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] bg-clip-text text-transparent mb-4"
                >
                  {laptop.brand?.name} {laptop.series} {laptop.model}
                </motion.h1>

                <motion.div
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  transition={{ duration: 0.5, delay: 0.3 }}
                  className="flex items-center gap-4 mb-6"
                >
                  {laptop.average_rating > 0 ? (
                    renderStars(laptop.average_rating)
                  ) : (
                    <span className="text-muted-foreground">Belum ada rating</span>
                  )}
                </motion.div>

                <motion.div
                  initial={{ opacity: 0, scale: 0.9 }}
                  animate={{ opacity: 1, scale: 1 }}
                  transition={{ duration: 0.5, delay: 0.4 }}
                  className="text-3xl font-bold text-[var(--emerald-600)] dark:text-[var(--emerald-500)] mb-8"
                >
                  Rp {Number(laptop.price).toLocaleString("id-ID")}
                </motion.div>

                {/* Specifications Grid */}
                <motion.div
                  variants={containerVariants}
                  initial="hidden"
                  animate="visible"
                  className="grid grid-cols-1 md:grid-cols-2 gap-4"
                >
                  <motion.div variants={itemVariants}>
                    <SpecItem
                      icon={Cpu}
                      title="Processor"
                      value={laptop.cpu}
                      color="bg-gradient-to-br from-[var(--blue-600)] to-[var(--violet-600)]"
                    />
                  </motion.div>
                  <motion.div variants={itemVariants}>
                    <SpecItem
                      icon={MemoryStick}
                      title="RAM"
                      value={`${laptop.ram} GB`}
                      color="bg-gradient-to-br from-[var(--emerald-600)] to-[var(--teal-600)]"
                    />
                  </motion.div>
                  <motion.div variants={itemVariants}>
                    <SpecItem
                      icon={HardDrive}
                      title="Penyimpanan"
                      value={`${laptop.storage} GB`}
                      color="bg-gradient-to-br from-[var(--purple-600)] to-[var(--pink-600)]"
                    />
                  </motion.div>
                  <motion.div variants={itemVariants}>
                    <SpecItem
                      icon={Monitor}
                      title="GPU"
                      value={laptop.gpu}
                      color="bg-gradient-to-br from-[var(--amber-600)] to-[var(--orange-600)]"
                    />
                  </motion.div>
                </motion.div>
              </div>
            </div>
          </motion.div>

          {/* Reviews Section */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="bg-card rounded-2xl shadow-lg p-8 border border-border"
          >
            <motion.h2
              initial={{ opacity: 0, x: -20 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.5, delay: 0.4 }}
              className="text-2xl font-extrabold text-foreground mb-6 flex items-center gap-2"
            >
              <MessageSquare className="w-6 h-6 text-[var(--blue-600)]" />
              Ulasan Pengguna
            </motion.h2>

            <AnimatePresence>
              {laptop.reviews.length > 0 ? (
                <motion.div variants={containerVariants} initial="hidden" animate="visible" className="space-y-6">
                  {laptop.reviews.map((review, index) => (
                    <motion.div
                      key={review.id}
                      variants={itemVariants}
                      custom={index}
                      className="group p-6 rounded-xl border border-border hover:bg-secondary/50 transition-colors"
                    >
                      <div className="flex items-start justify-between gap-4">
                        <div className="flex-1">
                          <div className="flex items-center gap-3 mb-2">
                            <motion.div
                              whileHover={{ scale: 1.1 }}
                              className="flex items-center justify-center w-10 h-10 bg-[var(--blue-500)/10] dark:bg-[var(--blue-500)/20] rounded-full"
                            >
                              <span className="font-medium text-[var(--blue-600)] dark:text-[var(--blue-400)]">
                                {review.responder_name[0]}
                              </span>
                            </motion.div>
                            <div>
                              <h3 className="font-medium text-foreground">{review.responder_name}</h3>
                              <div className="mt-1 flex items-center gap-2">
                                {renderStars(review.rating)}
                                <span className="text-xs text-muted-foreground flex items-center gap-1">
                                  <Calendar className="w-3 h-3" />
                                  {review.created_at}
                                </span>
                              </div>
                            </div>
                          </div>
                          {review.review && (
                            <p className="mt-4 text-muted-foreground group-hover:text-foreground transition-colors">
                              {review.review}
                            </p>
                          )}
                        </div>
                      </div>
                    </motion.div>
                  ))}
                </motion.div>
              ) : (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.5, delay: 0.5 }}
                  className="text-center py-8"
                >
                  <div className="w-16 h-16 mx-auto bg-[var(--blue-500)/10] rounded-full flex items-center justify-center mb-4">
                    <MessageSquare className="w-8 h-8 text-[var(--blue-600)]" />
                  </div>
                  <p className="text-muted-foreground">Belum ada ulasan untuk produk ini</p>
                  <motion.div whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }} className="mt-6">
                    <Button className="bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground">
                      Tulis Ulasan Pertama
                    </Button>
                  </motion.div>
                </motion.div>
              )}
            </AnimatePresence>
          </motion.div>
        </div>
      </div>
    </AppLayout>
  )
}
