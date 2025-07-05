import type React from "react"
import { useCallback } from "react"
import { Head, Link } from "@inertiajs/react"
import { router } from "@inertiajs/react"
import AppLayout from "@/layouts/app-layout"
import { route } from "ziggy-js"

import {
    CpuIcon,
    MemoryStick,
    HardDrive,
    Monitor,
    UserIcon,
    Shield,
    DollarSign,
    UploadCloud,
    Trash2,
    BarChart3,
    TrendingUp,
    TrendingDown,
    LaptopIcon,
} from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from "@/components/ui/card"
import type { Laptop, User } from "@/types"
import toast from "react-hot-toast"
import { Toaster } from "react-hot-toast"
import { motion } from "framer-motion"

interface Props {
    laptops: Laptop[]
    users: User[]

    stats: {
        total_laptops: number
        total_users: number
        total_brands: number
        avg_price: number
    }
}

interface StatCardProps {
    title: string
    value: string | number
    icon: React.ElementType
    trend: string
    color: string
    trendUp: boolean
}

interface LaptopCardProps {
    laptop: Laptop
    onDelete: (id: number) => void
}

export default function DashboardAdmin({ laptops, stats }: Props) {

    const handleDeleteConfirm = useCallback(
        (id: number) => {
        router.delete(route("laptops.destroy", id), {
            onSuccess: () => toast.success("Laptop berhasil dihapus!"),
            onError: (errors) => toast.error(errors.message || "Gagal menghapus laptop!"),
        })
        },
        [],
    )

    const handleDeleteLaptop = useCallback(
        (id: number) => {
            toast.custom((t) => <ConfirmDeleteToast toastId={t.id} onConfirm={() => handleDeleteConfirm(id)} />, {
                duration: Number.POSITIVE_INFINITY,
                position: "top-center",
            })
        },
        [handleDeleteConfirm],
    )

    return (
        <AppLayout>
        <Head title="Admin Dashboard" />
        <Toaster position="top-right" />

        <div className="p-4 md:p-6 space-y-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
            <div className="mb-6">
            <h1 className="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <BarChart3 className="w-7 h-7 text-indigo-500" />
                Dashboard Admin
            </h1>
            <p className="text-gray-500 dark:text-gray-400 mt-1">Kelola data laptop</p>
            </div>

            <StatsSection stats={stats} />
            <LaptopsSection laptops={laptops} onDelete={handleDeleteLaptop} />
        </div>
        </AppLayout>
    )
}

const StatsSection = ({ stats }: Pick<Props, "stats">) => {
    const statsConfig = [
        {
        title: "Total Laptop",
        value: stats.total_laptops,
        icon: Monitor,
        trend: "+12.3%",
        color: "from-blue-500 to-indigo-600",
        trendUp: true,
        },
        {
        title: "Total Pengguna",
        value: stats.total_users,
        icon: UserIcon,
        trend: "+5.2%",
        color: "from-emerald-500 to-green-600",
        trendUp: true,
        },
        {
        title: "Total Merek",
        value: stats.total_brands,
        icon: Shield,
        trend: "+2.4%",
        color: "from-purple-500 to-violet-600",
        trendUp: true,
        },
        {
        title: "Rata-Rata Harga",
        value: `Rp ${Number(stats.avg_price).toLocaleString("id-ID")}`,
        icon: DollarSign,
        trend: "-3.1%",
        color: "from-amber-500 to-orange-600",
        trendUp: false,
        },
    ]

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {statsConfig.map((stat, index) => (
            <motion.div
            key={index}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, delay: index * 0.1 }}
            >
            <StatCard {...stat} />
            </motion.div>
        ))}
        </div>
    )
}

const LaptopsSection = ({ laptops, onDelete }: { laptops: Laptop[]; onDelete: (id: number) => void }) => (
    <div className="space-y-4">
        <div className="flex items-center justify-between">
        <h2 className="text-xl md:text-2xl font-bold flex items-center gap-2 text-gray-800 dark:text-white">
            <LaptopIcon className="w-6 h-6 text-indigo-500" />
            Daftar Laptop
            <Badge variant="outline" className="ml-2 bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
            {laptops.length} item
            </Badge>
        </h2>
        <Link href={route("admin.upload-data")}>
            <Button className="bg-gradient-to-br from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white shadow-md hover:shadow-lg transition-all">
            <UploadCloud className="w-4 h-4 mr-2 text-green-100" />
            Upload Data
            </Button>
        </Link>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {laptops.map((laptop, index) => (
            <motion.div
            key={laptop.id}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, delay: index * 0.05 }}
            >
            <LaptopCard laptop={laptop} onDelete={onDelete} />
            </motion.div>
        ))}
        </div>
    </div>
)

const StatCard = ({ title, value, icon: Icon, trend, color, trendUp }: StatCardProps) => (
    <Card className="border-none shadow-md hover:shadow-lg transition-all overflow-hidden">
        <CardHeader className={`bg-gradient-to-br ${color} text-white p-4`}>
        <div className="flex items-center justify-between">
            <CardTitle className="text-lg font-medium">{title}</CardTitle>
            <Icon className="w-6 h-6 text-white/80" />
        </div>
        </CardHeader>
        <CardContent className="p-4 bg-white dark:bg-gray-800">
        <div className="flex items-end justify-between">
            <h3 className="text-2xl font-bold mt-1 text-gray-800 dark:text-white">{value}</h3>
            <Badge
            variant="outline"
            className={`flex items-center gap-1 ${trendUp ? "text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 dark:text-emerald-400" : "text-red-600 bg-red-50 dark:bg-red-900/20 dark:text-red-400"}`}
            >
            {trendUp ? <TrendingUp className="w-3 h-3" /> : <TrendingDown className="w-3 h-3" />}
            {trend}
            </Badge>
        </div>
        </CardContent>
    </Card>
)

const LaptopCard = ({ laptop, onDelete }: LaptopCardProps) => (
    <Card className="overflow-hidden border-none shadow-md hover:shadow-lg transition-all">
        <CardHeader className="p-4 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 border-b border-gray-100 dark:border-gray-700">
        <div className="flex items-start justify-between">
            <div>
            <CardTitle className="text-lg font-semibold text-gray-800 dark:text-white">
                {laptop.brand?.name} {laptop.series}
            </CardTitle>
            <p className="text-sm text-gray-500 dark:text-gray-400">{laptop.model}</p>
            </div>
            <PriceBadge price={laptop.price} />
        </div>
        </CardHeader>
        <CardContent className="p-4 bg-white dark:bg-gray-800">
        <SpecsList laptop={laptop} />
        </CardContent>
        <CardFooter className="p-4 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
        <p className="text-sm text-gray-500 dark:text-gray-400">
            {new Date(laptop.created_at).toLocaleDateString("id-ID", {
            day: "numeric",
            month: "short",
            year: "numeric",
            })}
        </p>
        <Button size="sm" variant="destructive" onClick={() => onDelete(laptop.id)} className="h-8">
            <Trash2 className="w-4 h-4 mr-1" />
            Hapus
        </Button>
        </CardFooter>
    </Card>
)

const PriceBadge = ({ price }: { price: number }) => (
    <Badge className="bg-indigo-100 text-indigo-800 hover:bg-indigo-200 dark:bg-indigo-900 dark:text-indigo-200 dark:hover:bg-indigo-800 transition-colors">
        Rp {Number(price).toLocaleString("id-ID")}
    </Badge>
)

const SpecsList = ({ laptop }: { laptop: Laptop }) => (
    <div className="space-y-3">
        <SpecItem icon={CpuIcon} text={laptop.cpu} color="text-blue-500 bg-blue-50 dark:bg-blue-900/20" />
        <SpecItem
        icon={MemoryStick}
        text={`${laptop.ram} GB RAM`}
        color="text-green-500 bg-green-50 dark:bg-green-900/20"
        />
        <SpecItem
        icon={HardDrive}
        text={`${laptop.storage} GB`}
        color="text-purple-500 bg-purple-50 dark:bg-purple-900/20"
        />
        <SpecItem icon={Monitor} text={laptop.gpu} color="text-orange-500 bg-orange-50 dark:bg-orange-900/20" />
    </div>
)

const SpecItem = ({ icon: Icon, text, color }: { icon: React.ElementType; text: string; color: string }) => (
    <div className="flex items-center gap-3 text-sm">
        <div className={`p-1.5 rounded-md ${color}`}>
        <Icon className="w-4 h-4" />
        </div>
        <span className="text-gray-700 dark:text-gray-300 font-medium">{text}</span>
    </div>
)

const ConfirmDeleteToast = ({ toastId, onConfirm }: { toastId: string; onConfirm: () => void }) => (
    <motion.div
        initial={{ opacity: 0, scale: 0.9 }}
        animate={{ opacity: 1, scale: 1 }}
        className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 max-w-sm mx-auto"
    >
        <div className="text-center space-y-4">
        <div className="bg-red-100 dark:bg-red-900/30 w-16 h-16 rounded-full flex items-center justify-center mx-auto">
            <Trash2 className="w-8 h-8 text-red-500 dark:text-red-400" />
        </div>
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white">Hapus Laptop?</h3>
        <p className="text-gray-600 dark:text-gray-400">
            Apakah Anda yakin ingin menghapus laptop ini? Tindakan ini tidak dapat dibatalkan.
        </p>
        <div className="flex justify-center gap-3 mt-4">
            <Button variant="outline" onClick={() => toast.dismiss(toastId)} className="w-full">
            Batal
            </Button>
                <Button
                    variant="destructive"
                    onClick={() => {
                        onConfirm()
                        toast.dismiss(toastId) // 👉 tambahkan ini agar toast ditutup
                    }}
                    className="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700"
                    >
                    Ya, Hapus
                </Button>
        </div>
        </div>
    </motion.div>
)
