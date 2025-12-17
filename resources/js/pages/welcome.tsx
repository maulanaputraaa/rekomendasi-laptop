import type { SharedData } from "@/types"
import { Head, Link, usePage } from "@inertiajs/react"
import { Search, Cpu, BarChart2, MonitorCheck, Shield, ArrowRight, Laptop, Stars, ChevronRight } from "lucide-react"
import { motion } from "framer-motion"

export default function Welcome() {
  const { auth } = usePage<SharedData>().props

  const features = [
    {
      icon: <Search className="w-6 h-6" />,
      title: "Pencarian Cerdas",
      description: "Temukan laptop sesuai kebutuhan dengan algoritma rekomendasi canggih",
      color: "from-[var(--blue-500)] to-[var(--violet-600)]",
    },
    {
      icon: <BarChart2 className="w-6 h-6" />,
      title: "Rating dan Ulasan",
      description: "Rating dan review langsung dari para pengguna yang berpengalaman",
      color: "from-[var(--purple-500)] to-[var(--pink-600)]",
    },
    {
      icon: <MonitorCheck className="w-6 h-6" />,
      title: "Rekomendasi Akurat",
      description: "Hasil rekomendasi berdasarkan kebutuhan dan budget yang Anda miliki",
      color: "from-[var(--emerald-500)] to-[var(--teal-600)]",
    },
    {
      icon: <Shield className="w-6 h-6" />,
      title: "Data Terpercaya",
      description: "Database lengkap dengan spesifikasi terkini dari berbagai merek laptop",
      color: "from-[var(--amber-500)] to-[var(--orange-600)]",
    },
  ]

  const laptopTypes = [
    { name: "Gaming", specs: "Performa tinggi, GPU kuat", icon: <Laptop className="w-5 h-5" /> },
    { name: "Kantor", specs: "Ringan, baterai tahan lama", icon: <Cpu className="w-5 h-5" /> },
    { name: "Desain", specs: "Layar berkualitas, warna akurat", icon: <Stars className="w-5 h-5" /> },
    { name: "Pelajar", specs: "Terjangkau, multitasking", icon: <MonitorCheck className="w-5 h-5" /> },
  ]

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
        delayChildren: 0.1,
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
    <>
      <Head title="Sistem Rekomendasi Laptop">
        <link
          href="https://fonts.bunny.net/css?family=poppins:400,500,600,700|space-grotesk:500,700"
          rel="stylesheet"
        />
      </Head>

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

      <div className="min-h-screen flex flex-col bg-background text-foreground overflow-hidden">
        {/* Header */}
        <motion.header
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="w-full py-6 px-4 sm:px-6 lg:px-8 border-b border-border backdrop-blur-sm bg-background/80 sticky top-0 z-50"
        >
          <div className="max-w-7xl mx-auto flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[var(--blue-600)] to-[var(--violet-600)] flex items-center justify-center shadow-lg">
                <Laptop className="w-5 h-5 text-primary-foreground" />
              </div>
              <span className="text-xl font-bold bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] bg-clip-text text-transparent">
                LaptopFinder
              </span>
            </div>

            <nav className="flex items-center gap-4">
              {auth.user ? (
                <Link
                  href={auth.user?.role === "admin" ? route("admin.dashboard") : route("dashboard")}
                  className="px-5 py-2.5 rounded-lg bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground font-medium shadow-md hover:shadow-lg transition-all hover:scale-105 active:scale-95"
                >
                  Dashboard
                </Link>
              ) : (
                <>
                  <Link
                    href={route("login")}
                    className="px-5 py-2.5 rounded-lg border border-border font-medium hover:bg-secondary transition-colors"
                  >
                    Masuk
                  </Link>
                  <Link
                    href={route("register")}
                    className="px-5 py-2.5 rounded-lg bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground font-medium shadow-md hover:shadow-lg transition-all hover:scale-105 active:scale-95"
                  >
                    Daftar
                  </Link>
                </>
              )}
            </nav>
          </div>
        </motion.header>

        {/* Main Content */}
        <main className="flex-1">
          {/* Hero Section */}
          <section className="relative py-20 px-4 sm:px-6 lg:px-8 overflow-hidden">
            <div className="max-w-7xl mx-auto">
              <motion.div
                initial={{ opacity: 0, y: 30 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.7 }}
                className="text-center max-w-3xl mx-auto space-y-8"
              >
                <h1 className="text-4xl sm:text-5xl md:text-6xl font-bold tracking-tight">
                  Temukan{" "}
                  <span className="bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] bg-clip-text text-transparent">
                    Laptop Ideal
                  </span>{" "}
                  Untuk Kebutuhan Anda
                </h1>

                <p className="text-lg sm:text-xl text-muted-foreground max-w-2xl mx-auto">
                  Sistem rekomendasi cerdas yang membantu Anda menemukan laptop impian dengan metode hybrid untuk hasil
                  yang optimal.
                </p>

                <div className="flex flex-col sm:flex-row gap-4 justify-center pt-4">
                  <Link
                    href={
                      auth.user
                        ? auth.user.role === "admin"
                          ? route("admin.dashboard")
                          : route("dashboard")
                        : route("login")
                    }
                    className="px-8 py-3.5 rounded-lg bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground font-medium shadow-lg hover:shadow-xl transition-all hover:scale-105 active:scale-95 flex items-center justify-center gap-2 group"
                  >
                    <span>Mulai Sekarang</span>
                    <ChevronRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                  </Link>

                  <Link
                    href="#fitur"
                    className="px-8 py-3.5 rounded-lg border border-border font-medium hover:bg-secondary transition-colors flex items-center justify-center"
                  >
                    Pelajari Fitur
                  </Link>
                </div>
              </motion.div>

              {/* Floating Laptop Image */}
              <motion.div
                initial={{ opacity: 0, y: 50 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.8, delay: 0.3 }}
                className="mt-16 relative max-w-4xl mx-auto"
              >
                <div className="relative z-10 rounded-xl overflow-hidden shadow-2xl border border-border">
                  <img src="/image/dashboard.png" alt="Laptop Finder Dashboard Preview" className="w-full h-auto" />
                </div>

                {/* Decorative elements */}
                <div className="absolute -bottom-6 -left-6 w-24 h-24 bg-gradient-to-br from-[var(--blue-500)] to-[var(--violet-500)] rounded-lg blur-xl opacity-30 dark:opacity-20"></div>
                <div className="absolute -top-6 -right-6 w-24 h-24 bg-gradient-to-br from-[var(--emerald-500)] to-[var(--teal-500)] rounded-lg blur-xl opacity-30 dark:opacity-20"></div>
              </motion.div>
            </div>
          </section>

          {/* Features Section */}
          <section id="fitur" className="py-20 px-4 sm:px-6 lg:px-8 bg-secondary/50">
            <div className="max-w-7xl mx-auto">
              <motion.div
                initial={{ opacity: 0 }}
                whileInView={{ opacity: 1 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5 }}
                className="text-center max-w-3xl mx-auto mb-16"
              >
                <h2 className="text-3xl sm:text-4xl font-bold mb-6">Fitur Unggulan</h2>
                <p className="text-lg text-muted-foreground">
                  Temukan laptop impian Anda dengan bantuan fitur-fitur canggih yang kami sediakan
                </p>
              </motion.div>

              <motion.div
                variants={containerVariants}
                initial="hidden"
                whileInView="visible"
                viewport={{ once: true }}
                className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8"
              >
                {features.map((feature, index) => (
                  <motion.div
                    key={index}
                    variants={itemVariants}
                    whileHover={{ y: -8 }}
                    className="bg-card rounded-xl p-8 shadow-lg border border-border relative overflow-hidden group"
                  >
                    <div
                      className={`absolute inset-0 bg-gradient-to-br ${feature.color} opacity-0 group-hover:opacity-5 dark:group-hover:opacity-10 transition-opacity duration-500`}
                    ></div>

                    <div
                      className={`w-14 h-14 rounded-xl bg-gradient-to-br ${feature.color} flex items-center justify-center mb-6 shadow-lg`}
                    >
                      <div className="text-primary-foreground">{feature.icon}</div>
                    </div>

                    <h3 className="text-xl font-semibold mb-3">{feature.title}</h3>
                    <p className="text-muted-foreground">{feature.description}</p>
                  </motion.div>
                ))}
              </motion.div>
            </div>
          </section>

          {/* Laptop Types Section */}
          <section className="py-20 px-4 sm:px-6 lg:px-8">
            <div className="max-w-7xl mx-auto">
              <div className="flex flex-col lg:flex-row gap-12 items-center">
                <motion.div
                  initial={{ opacity: 0, x: -30 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.6 }}
                  className="lg:w-1/2 space-y-6"
                >
                  <h2 className="text-3xl sm:text-4xl font-bold">Rekomendasi untuk Berbagai Kebutuhan</h2>
                  <p className="text-lg text-muted-foreground">
                    Kami memahami bahwa setiap pengguna memiliki kebutuhan yang berbeda. Sistem kami dapat memberikan
                    rekomendasi laptop yang sesuai dengan kebutuhan spesifik Anda.
                  </p>

                  <div className="space-y-4 pt-4">
                    {laptopTypes.map((type, index) => (
                      <motion.div
                        key={index}
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true }}
                        transition={{ duration: 0.4, delay: index * 0.1 }}
                        className="flex items-center gap-4 p-4 rounded-lg border border-border bg-card shadow-sm hover:shadow-md transition-shadow"
                      >
                        <div className="w-10 h-10 rounded-full bg-[var(--blue-500)/10] dark:bg-[var(--blue-600)/30] flex items-center justify-center text-[var(--blue-600)]">
                          {type.icon}
                        </div>
                        <div>
                          <h3 className="font-medium">{type.name}</h3>
                          <p className="text-sm text-muted-foreground">{type.specs}</p>
                        </div>
                      </motion.div>
                    ))}
                  </div>
                </motion.div>

                <motion.div
                  initial={{ opacity: 0, x: 30 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.6 }}
                  className="lg:w-1/2 relative"
                >
                  <div className="relative z-10 rounded-xl overflow-hidden shadow-xl border border-border">
                    <img
                      src="/image/detail laptop.png"
                      alt="Laptop Recommendation Interface"
                      className="w-full h-auto"
                    />
                  </div>

                  {/* Decorative elements */}
                  <div className="absolute -top-6 -left-6 w-32 h-32 bg-gradient-to-br from-[var(--blue-500)] to-[var(--violet-500)] rounded-full blur-xl opacity-20 dark:opacity-10 z-0"></div>
                  <div className="absolute -bottom-6 -right-6 w-32 h-32 bg-gradient-to-br from-[var(--emerald-500)] to-[var(--teal-500)] rounded-full blur-xl opacity-20 dark:opacity-10 z-0"></div>
                </motion.div>
              </div>
            </div>
          </section>

          {/* CTA Section */}
          <section className="py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-[var(--blue-600)] to-[var(--violet-600)] text-primary-foreground">
            <motion.div
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.7 }}
              className="max-w-4xl mx-auto text-center space-y-8"
            >
              <h2 className="text-3xl sm:text-4xl font-bold">Siap Menemukan Laptop Impian Anda?</h2>
              <p className="text-lg text-[var(--blue-500)/90] max-w-2xl mx-auto">
                Mulai perjalanan Anda untuk menemukan laptop yang sempurna sesuai dengan kebutuhan dan anggaran Anda.
              </p>

              <Link
                href={
                  auth.user
                    ? auth.user.role === "admin"
                      ? route("admin.dashboard")
                      : route("dashboard")
                    : route("register")
                }
                className="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-lg bg-white text-[var(--blue-600)] font-medium shadow-lg hover:shadow-xl transition-all hover:scale-105 active:scale-95 group"
              >
                <span>{auth.user ? "Ke Dashboard" : "Daftar Sekarang"}</span>
                <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </Link>
            </motion.div>
          </section>
        </main>

        {/* Footer */}
        <footer className="py-12 px-4 sm:px-6 lg:px-8 border-t border-border">
          <div className="max-w-7xl mx-auto">
            <div className="flex flex-col md:flex-row justify-between items-center gap-6">
              <div className="flex items-center gap-2">
                <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[var(--blue-600)] to-[var(--violet-600)] flex items-center justify-center shadow-lg">
                  <Laptop className="w-5 h-5 text-primary-foreground" />
                </div>
                <span className="text-xl font-bold bg-gradient-to-r from-[var(--blue-600)] to-[var(--violet-600)] bg-clip-text text-transparent">
                  LaptopFinder
                </span>
              </div>

              <p className="text-sm text-muted-foreground">
                © {new Date().getFullYear()} Laptop Finder
                <span className="mx-2">•</span>
                <span className="opacity-75">Dikembangkan oleh Putra</span>
              </p>
            </div>
          </div>
        </footer>
      </div>
    </>
  )
}
