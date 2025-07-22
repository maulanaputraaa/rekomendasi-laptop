import { Head, router, usePage, useForm } from "@inertiajs/react"
import { route } from "ziggy-js"
import AppLayout from "@/layouts/app-layout"
import { Button } from "@/components/ui/button"
import {
  UserPlus,
  Pencil,
  Trash2,
  Users,
  UserCog,
  Shield,
  Mail,
  Search,
  UserCircle,
  BarChart3,
  Clock,
} from "lucide-react"
import { useState, useEffect } from "react"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { toast, Toaster } from "react-hot-toast"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardHeader, CardFooter } from "@/components/ui/card"
import { motion, AnimatePresence } from "framer-motion"

interface User {
  id: number
  name: string
  email: string
  role: string
}

interface FlashProps {
  success?: string
  error?: string
}

interface Props {
  users: User[]
  flash?: FlashProps
}

export default function UserList({ users }: Props) {
  const { flash } = usePage().props as { flash?: FlashProps }
  const [createModal, setCreateModal] = useState(false)
  const [editModal, setEditModal] = useState(false)
  const [deleteModal, setDeleteModal] = useState(false)
  const [selectedUser, setSelectedUser] = useState<User | null>(null)
  const [searchTerm, setSearchTerm] = useState("")
  const [filteredUsers, setFilteredUsers] = useState<User[]>(users)

  useEffect(() => {
    if (searchTerm.trim() === "") {
      setFilteredUsers(users)
    } else {
      const filtered = users.filter(
        (user) =>
          user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
          user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
          user.role.toLowerCase().includes(searchTerm.toLowerCase()),
      )
      setFilteredUsers(filtered)
    }
  }, [searchTerm, users])

  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success)
    }
    if (flash?.error) {
      toast.error(flash.error)
    }
  }, [flash])

  const {
    data: createData,
    setData: setCreateData,
    post,
    processing: creating,
    errors: createErrors,
    reset: resetCreate,
  } = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    role: "user",
  })

  const {
    data: editData,
    setData: setEditData,
    put,
    processing: editing,
    errors: editErrors,
    reset: resetEdit,
  } = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    role: "user",
  })

  const handleCreate = () => {
    post(route("admin.users.store"), {
      onSuccess: () => {
        toast.success("Pengguna berhasil ditambahkan")
        setCreateModal(false)
        resetCreate()
      },
    })
  }

  const handleEdit = () => {
    if (!selectedUser) return

    put(route("admin.users.update", selectedUser.id), {
      onSuccess: () => {
        toast.success("Pengguna berhasil diperbarui")
        setEditModal(false)
        resetEdit()
      },
    })
  }

  const handleDelete = () => {
    if (!selectedUser) return

    router.delete(route("admin.users.destroy", selectedUser.id), {
      onSuccess: () => {
        toast.success("Pengguna berhasil dihapus")
        setDeleteModal(false)
      },
    })
  }

  const openEditModal = (user: User) => {
    setSelectedUser(user)
    setEditData({
      name: user.name,
      email: user.email,
      password: "",
      password_confirmation: "",
      role: user.role,
    })
    setEditModal(true)
  }

  const adminCount = filteredUsers.filter((u) => u.role === "admin").length
  const userCount = filteredUsers.filter((u) => u.role === "user").length

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
        delayChildren: 0.2,
      },
    },
  }

  const itemVariants = {
    hidden: { y: 20, opacity: 0 },
    visible: {
      y: 0,
      opacity: 1,
      transition: { type: "spring", stiffness: 100 },
    },
  }

  const tableRowVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: (i: number) => ({
      opacity: 1,
      y: 0,
      transition: {
        delay: i * 0.05,
        duration: 0.3,
        ease: "easeOut",
      },
    }),
    exit: { opacity: 0, y: -20, transition: { duration: 0.2 } },
  }

  return (
    <AppLayout>
      <Head title="User Management" />
      <Toaster position="top-right" />

      <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
        <motion.div
          className="p-6"
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
        >
          <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
              <motion.h1
                className="text-2xl md:text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-2"
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.5, delay: 0.2 }}
              >
                <BarChart3 className="w-7 h-7 text-violet-500" />
                Dashboard User
              </motion.h1>
              <motion.p
                className="text-gray-500 dark:text-gray-400 mt-1"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.5, delay: 0.3 }}
              >
                Kelola data pengguna dan izin akses
              </motion.p>
            </div>
          </div>

          <motion.div
            className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6"
            variants={containerVariants}
            initial="hidden"
            animate="visible"
          >
            <motion.div variants={itemVariants}>
              <Card className="border shadow-sm bg-white dark:bg-gray-800 overflow-hidden">
                <CardContent className="p-6">
                  <div className="flex justify-between items-center">
                    <div>
                      <p className="text-sm text-gray-500 dark:text-gray-400">Total Pengguna</p>
                      <h3 className="text-2xl font-bold mt-1 text-gray-800 dark:text-white">{filteredUsers.length}</h3>
                    </div>
                    <motion.div
                      className="w-12 h-12 bg-violet-100 dark:bg-violet-900/30 rounded-full flex items-center justify-center"
                      whileHover={{ scale: 1.1, rotate: 5 }}
                      transition={{ type: "spring", stiffness: 400, damping: 10 }}
                    >
                      <Users className="w-6 h-6 text-violet-600 dark:text-violet-400" />
                    </motion.div>
                  </div>
                </CardContent>
              </Card>
            </motion.div>

            <motion.div variants={itemVariants}>
              <Card className="border shadow-sm bg-white dark:bg-gray-800 overflow-hidden">
                <CardContent className="p-6">
                  <div className="flex justify-between items-center">
                    <div>
                      <p className="text-sm text-gray-500 dark:text-gray-400">Admin</p>
                      <h3 className="text-2xl font-bold mt-1 text-gray-800 dark:text-white">{adminCount}</h3>
                    </div>
                    <motion.div
                      className="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center"
                      whileHover={{ scale: 1.1, rotate: 5 }}
                      transition={{ type: "spring", stiffness: 400, damping: 10 }}
                    >
                      <Shield className="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </motion.div>
                  </div>
                </CardContent>
              </Card>
            </motion.div>

            <motion.div variants={itemVariants}>
              <Card className="border shadow-sm bg-white dark:bg-gray-800 overflow-hidden">
                <CardContent className="p-6">
                  <div className="flex justify-between items-center">
                    <div>
                      <p className="text-sm text-gray-500 dark:text-gray-400">Pengguna</p>
                      <h3 className="text-2xl font-bold mt-1 text-gray-800 dark:text-white">{userCount}</h3>
                    </div>
                    <motion.div
                      className="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center"
                      whileHover={{ scale: 1.1, rotate: 5 }}
                      transition={{ type: "spring", stiffness: 400, damping: 10 }}
                    >
                      <UserCircle className="w-6 h-6 text-green-600 dark:text-green-400" />
                    </motion.div>
                  </div>
                </CardContent>
              </Card>
            </motion.div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.4 }}
            className="mb-6"
          >
            <Card className="border shadow-sm bg-white dark:bg-gray-800 overflow-hidden">
              <CardHeader className="bg-violet-600 text-white p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div className="flex items-center gap-3">
                  <motion.div
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    transition={{ type: "spring", stiffness: 260, damping: 20, delay: 0.5 }}
                  >
                    <Users className="h-7 w-7" />
                  </motion.div>
                  <div>
                    <motion.h1
                      className="text-2xl font-bold"
                      initial={{ opacity: 0, x: -20 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ duration: 0.5, delay: 0.6 }}
                    >
                      Manajemen Pengguna
                    </motion.h1>
                    <motion.p
                      className="text-violet-100 text-sm"
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      transition={{ duration: 0.5, delay: 0.7 }}
                    >
                      Kelola akun dan izin pengguna
                    </motion.p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <motion.div
                    className="relative flex-1 md:w-64"
                    initial={{ width: "0%" }}
                    animate={{ width: "100%" }}
                    transition={{ duration: 0.5, delay: 0.8 }}
                  >
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-white/60 h-4 w-4" />
                    <Input
                      placeholder="Cari pengguna..."
                      className="pl-9 bg-white/20 border-white/20 text-white placeholder:text-white/70 w-full"
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                    />
                  </motion.div>
                  <motion.div
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    transition={{ type: "spring", stiffness: 260, damping: 20, delay: 0.9 }}
                  >
                    <Button
                      onClick={() => setCreateModal(true)}
                      className="bg-white text-violet-600 hover:bg-violet-100 hover:text-violet-700"
                    >
                      <UserPlus className="w-4 h-4 mr-2" />
                      Tambah Pengguna
                    </Button>
                  </motion.div>
                </div>
              </CardHeader>
              <CardContent className="p-0">
                <div className="px-4 pb-4 pt-4">
                  <div className="rounded-lg border overflow-hidden">
                    <table className="w-full">
                      <thead className="bg-gray-50 dark:bg-gray-900 border-b dark:border-gray-700">
                        <tr>
                          <th className="px-6 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                            Nama
                          </th>
                          <th className="px-6 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email
                          </th>
                          <th className="px-6 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">
                            Peran
                          </th>
                          <th className="px-6 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                            Tindakan
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <AnimatePresence>
                          {filteredUsers.map((user, index) => (
                            <motion.tr
                              key={user.id}
                              custom={index}
                              variants={tableRowVariants}
                              initial="hidden"
                              animate="visible"
                              exit="exit"
                              className="hover:bg-gray-50 dark:hover:bg-gray-700 border-b dark:border-gray-700 last:border-b-0"
                              whileHover={{ backgroundColor: "#f5f3ff" }}
                            >
                              <td className="px-6 py-4 whitespace-nowrap">
                                <div className="flex items-center gap-3">
                                  <motion.div
                                    className="w-9 h-9 rounded-full bg-violet-500 flex items-center justify-center text-white font-semibold"
                                    whileHover={{ scale: 1.1 }}
                                    transition={{ type: "spring", stiffness: 400, damping: 10 }}
                                  >
                                    {user.name.charAt(0).toUpperCase()}
                                  </motion.div>
                                  <span className="font-medium text-gray-800 dark:text-gray-200">{user.name}</span>
                                </div>
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap">
                                <div className="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                  <Mail className="h-4 w-4 text-gray-400" />
                                  {user.email}
                                </div>
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap">
                                <Badge
                                  className={`px-2 py-1 ${user.role === "admin"
                                      ? "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100"
                                      : "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100"
                                    }`}
                                >
                                  {user.role === "admin" ? (
                                    <Shield className="w-3 h-3 mr-1 inline" />
                                  ) : (
                                    <UserCog className="w-3 h-3 mr-1 inline" />
                                  )}
                                  {user.role}
                                </Badge>
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-right space-x-2">
                                <motion.div
                                  whileHover={{ scale: 1.05 }}
                                  whileTap={{ scale: 0.95 }}
                                  className="inline-block"
                                >
                                  <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => openEditModal(user)}
                                    className="border-violet-200 text-violet-700 hover:bg-violet-50 dark:border-violet-800 dark:text-violet-400 dark:hover:bg-violet-900/50"
                                  >
                                    <Pencil className="w-4 h-4 mr-1" />
                                    Edit
                                  </Button>
                                </motion.div>
                                <motion.div
                                  whileHover={{ scale: 1.05 }}
                                  whileTap={{ scale: 0.95 }}
                                  className="inline-block"
                                >
                                  <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => {
                                      setSelectedUser(user)
                                      setDeleteModal(true)
                                    }}
                                    className="bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50"
                                  >
                                    <Trash2 className="w-4 h-4 mr-1" />
                                    Delete
                                  </Button>
                                </motion.div>
                              </td>
                            </motion.tr>
                          ))}
                        </AnimatePresence>
                      </tbody>
                    </table>
                  </div>
                </div>
              </CardContent>
              <CardFooter className="bg-gray-50 dark:bg-gray-900/50 border-t p-4 flex justify-between items-center">
                <div className="text-sm text-gray-500 dark:text-gray-400">
                  Menampilkan {filteredUsers.length} dari {users.length} pengguna
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant="outline" className="flex items-center gap-1">
                    <Clock className="w-3 h-3" />
                    Terakhir diperbarui: {new Date().toLocaleDateString()}
                  </Badge>
                </div>
              </CardFooter>
            </Card>
          </motion.div>
        </motion.div>

        {/* Create User Modal */}
        <AnimatePresence>
          {createModal && (
            <Dialog open={createModal} onOpenChange={setCreateModal}>
              <DialogContent className="sm:max-w-md">
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -20 }}
                  transition={{ duration: 0.3 }}
                >
                  <DialogHeader>
                    <DialogTitle className="text-xl">Buat Pengguna Baru</DialogTitle>
                  </DialogHeader>

                  <div className="space-y-4 py-2">
                    <div>
                      <Label className="text-sm font-medium">Nama</Label>
                      <Input
                        value={createData.name}
                        onChange={(e) => setCreateData("name", e.target.value)}
                        className="mt-1"
                      />
                      {createErrors.name && <p className="text-sm text-red-500 mt-1">{createErrors.name}</p>}
                    </div>

                    <div>
                      <Label className="text-sm font-medium">Email</Label>
                      <Input
                        type="email"
                        value={createData.email}
                        onChange={(e) => setCreateData("email", e.target.value)}
                        className="mt-1"
                      />
                      {createErrors.email && <p className="text-sm text-red-500 mt-1">{createErrors.email}</p>}
                    </div>

                    <div>
                      <Label className="text-sm font-medium">Kata Sandi</Label>
                      <Input
                        type="password"
                        value={createData.password}
                        onChange={(e) => setCreateData("password", e.target.value)}
                        className="mt-1"
                      />
                      {createErrors.password && <p className="text-sm text-red-500 mt-1">{createErrors.password}</p>}
                    </div>

                    <div>
                      <Label className="text-sm font-medium">Konfirmasi Kata Sandi</Label>
                      <Input
                        type="password"
                        value={createData.password_confirmation}
                        onChange={(e) => setCreateData("password_confirmation", e.target.value)}
                        className="mt-1"
                      />
                    </div>

                    <div>
                      <Label className="text-sm font-medium">Peran</Label>
                      <Select value={createData.role} onValueChange={(value) => setCreateData("role", value)}>
                        <SelectTrigger className="mt-1">
                          <SelectValue placeholder="Pilih peran" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="admin">Admin</SelectItem>
                          <SelectItem value="user">User</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  <DialogFooter className="flex space-x-2 justify-end">
                    <Button variant="outline" onClick={() => setCreateModal(false)}>
                      Batal
                    </Button>
                    <motion.div whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}>
                      <Button
                        onClick={handleCreate}
                        disabled={creating}
                        className="bg-violet-600 text-white hover:bg-violet-700"
                      >
                        {creating ? "Membuat..." : "Buat Pengguna"}
                      </Button>
                    </motion.div>
                  </DialogFooter>
                </motion.div>
              </DialogContent>
            </Dialog>
          )}
        </AnimatePresence>

        {/* Edit User Modal */}
        <AnimatePresence>
          {editModal && (
            <Dialog open={editModal} onOpenChange={setEditModal}>
              <DialogContent className="sm:max-w-md">
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -20 }}
                  transition={{ duration: 0.3 }}
                >
                  <DialogHeader>
                    <DialogTitle className="text-xl">Edit Pengguna</DialogTitle>
                  </DialogHeader>

                  <div className="space-y-4 py-2">
                    <div>
                      <Label className="text-sm font-medium">Nama</Label>
                      <Input
                        value={editData.name}
                        onChange={(e) => setEditData("name", e.target.value)}
                        className="mt-1"
                      />
                      {editErrors.name && <p className="text-sm text-red-500 mt-1">{editErrors.name}</p>}
                    </div>

                    <div>
                      <Label className="text-sm font-medium">Email</Label>
                      <Input
                        type="email"
                        value={editData.email}
                        onChange={(e) => setEditData("email", e.target.value)}
                        className="mt-1"
                      />
                      {editErrors.email && <p className="text-sm text-red-500 mt-1">{editErrors.email}</p>}
                    </div>

                    <div>
                      <Label className="text-sm font-medium">Kata Sandi Baru</Label>
                      <Input
                        type="password"
                        value={editData.password}
                        onChange={(e) => setEditData("password", e.target.value)}
                        placeholder="Biarkan kosong untuk mempertahankan kata sandi saat ini"
                        className="mt-1"
                      />
                      {editErrors.password && <p className="text-sm text-red-500 mt-1">{editErrors.password}</p>}
                    </div>

                    <div>
                      <Label className="text-sm font-medium">Konfirmasi Kata Sandi</Label>
                      <Input
                        type="password"
                        value={editData.password_confirmation}
                        onChange={(e) => setEditData("password_confirmation", e.target.value)}
                        className="mt-1"
                      />
                    </div>

                    <div>
                      <Label className="text-sm font-medium">Peran</Label>
                      <Select value={editData.role} onValueChange={(value) => setEditData("role", value)}>
                        <SelectTrigger className="mt-1">
                          <SelectValue placeholder="Pilih peran" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="admin">Admin</SelectItem>
                          <SelectItem value="user">User</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  <DialogFooter className="flex space-x-2 justify-end">
                    <Button variant="outline" onClick={() => setEditModal(false)}>
                      Batal
                    </Button>
                    <motion.div whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}>
                      <Button
                        onClick={handleEdit}
                        disabled={editing}
                        className="bg-violet-600 text-white hover:bg-violet-700"
                      >
                        {editing ? "Memperbarui..." : "Simpan Perubahan"}
                      </Button>
                    </motion.div>
                  </DialogFooter>
                </motion.div>
              </DialogContent>
            </Dialog>
          )}
        </AnimatePresence>

        {/* Delete Confirmation Modal */}
        <AnimatePresence>
          {deleteModal && (
            <Dialog open={deleteModal} onOpenChange={setDeleteModal}>
              <DialogContent className="sm:max-w-md">
                <motion.div
                  initial={{ opacity: 0, scale: 0.9 }}
                  animate={{ opacity: 1, scale: 1 }}
                  exit={{ opacity: 0, scale: 0.9 }}
                  transition={{ duration: 0.3 }}
                >
                  <DialogHeader>
                    <DialogTitle className="text-xl">Konfirmasi Hapus</DialogTitle>
                  </DialogHeader>

                  <div className="py-4 text-center">
                    <motion.div
                      className="w-16 h-16 mx-auto bg-red-100 rounded-full flex items-center justify-center mb-4"
                      initial={{ rotate: 0 }}
                      animate={{ rotate: [0, -10, 10, -10, 10, 0] }}
                      transition={{ duration: 0.5, delay: 0.2 }}
                    >
                      <Trash2 className="h-8 w-8 text-red-600" />
                    </motion.div>

                    <p className="text-gray-600 dark:text-gray-400">
                      Apakah Anda yakin ingin menghapus pengguna{" "}
                      <span className="font-semibold">{selectedUser?.name}</span>?
                    </p>
                    <p className="text-sm text-gray-500 mt-2">Tindakan ini tidak dapat dibatalkan.</p>
                  </div>

                  <DialogFooter className="flex space-x-2 justify-end">
                    <Button variant="outline" onClick={() => setDeleteModal(false)}>
                      Batal
                    </Button>
                    <motion.div whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}>
                      <Button variant="destructive" onClick={handleDelete}>
                        Hapus Pengguna
                      </Button>
                    </motion.div>
                  </DialogFooter>
                </motion.div>
              </DialogContent>
            </Dialog>
          )}
        </AnimatePresence>
      </div>
    </AppLayout>
  )
}
