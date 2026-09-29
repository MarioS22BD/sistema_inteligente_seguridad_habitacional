<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Pencil,
    RefreshCw,
    Search,
    ShieldCheck,
    Trash2,
    UserPlus,
    Users,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes';
import { apiFetch } from '@/lib/apiFetch';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Usuarios', href: '/usuarios' },
        ],
    },
});

interface UsuarioApi {
    id: number;
    name: string;
    email: string;
    rol: 'administrador' | 'empleado' | 'usuario';
    created_at: string | null;
}

interface ApiValidationError {
    message?: string;
    errors?: Record<string, string[]>;
}

const page = usePage();
const currentUser = computed(
    () => page.props.auth.user as { rol?: string } | null,
);
const role = computed(() => currentUser.value?.rol?.toLowerCase() ?? 'usuario');
const isAdmin = computed(() => role.value === 'administrador');
const canManageUsers = computed(() =>
    ['administrador', 'empleado'].includes(role.value),
);
const loading = ref(true);
const refreshing = ref(false);
const submitting = ref(false);
const deleting = ref(false);
const users = ref<UsuarioApi[]>([]);
const search = ref('');
const error = ref<string | null>(null);
const validationErrors = ref<Record<string, string>>({});
const userDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const editingUser = ref<UsuarioApi | null>(null);
const userToDelete = ref<UsuarioApi | null>(null);
const form = ref({
    name: '',
    email: '',
    password: '',
    rol: 'usuario' as UsuarioApi['rol'],
});

const filteredUsers = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('es');
    if (!query) return users.value;

    return users.value.filter((user) =>
        `${user.name} ${user.email} ${user.rol}`
            .toLocaleLowerCase('es')
            .includes(query),
    );
});

const formatDate = (value: string | null): string => {
    if (!value) return 'Sin información';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Sin información';

    return new Intl.DateTimeFormat('es-ES', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const roleTone = (value: UsuarioApi['rol']): string => {
    if (value === 'administrador')
        return 'border-blue-300 bg-blue-50 text-blue-700';
    if (value === 'empleado')
        return 'border-amber-300 bg-amber-50 text-amber-800';
    return 'border-slate-300 bg-slate-100 text-slate-700';
};

const loadUsers = async (showFeedback = false): Promise<void> => {
    refreshing.value = true;

    try {
        const result: UsuarioApi[] = [];
        let nextPage: string | null = '/api/v1/usuarios?per_page=100';

        while (nextPage) {
            const response = await apiFetch(nextPage);
            if (!response.ok)
                throw new Error('No se pudo cargar el listado de usuarios.');

            const payload = (await response.json()) as {
                data?: UsuarioApi[];
                links?: { next?: string | null };
            };
            if (Array.isArray(payload.data)) result.push(...payload.data);
            nextPage = payload.links?.next ?? null;
        }

        users.value = result;
        error.value = null;
        if (showFeedback) toast.success('Listado de usuarios actualizado.');
    } catch (err) {
        error.value =
            err instanceof Error ? err.message : 'Error al cargar usuarios.';
        if (showFeedback) toast.error(error.value);
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

const resetForm = (): void => {
    form.value = { name: '', email: '', password: '', rol: 'usuario' };
    validationErrors.value = {};
    editingUser.value = null;
};

const openCreateDialog = (): void => {
    resetForm();
    userDialogOpen.value = true;
};

const openEditDialog = (user: UsuarioApi): void => {
    editingUser.value = user;
    form.value = {
        name: user.name,
        email: user.email,
        password: '',
        rol: user.rol,
    };
    validationErrors.value = {};
    userDialogOpen.value = true;
};

const saveUser = async (): Promise<void> => {
    if (submitting.value) return;
    submitting.value = true;
    validationErrors.value = {};

    try {
        const isEditing = editingUser.value !== null;
        const body: Record<string, string> = {
            name: form.value.name,
            email: form.value.email,
        };

        if (!isEditing || isAdmin.value) body.rol = form.value.rol;
        if (form.value.password.trim()) body.password = form.value.password;

        const response = await apiFetch(
            isEditing
                ? `/api/v1/usuarios/${editingUser.value?.id}`
                : '/api/v1/usuarios',
            {
                method: isEditing ? 'PATCH' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            },
        );

        if (response.status === 422) {
            const payload = (await response.json()) as ApiValidationError;
            validationErrors.value = Object.fromEntries(
                Object.entries(payload.errors ?? {}).map(
                    ([field, messages]) => [
                        field,
                        messages[0] ?? 'Dato inválido.',
                    ],
                ),
            );
            return;
        }

        if (!response.ok) throw new Error('No se pudo guardar el usuario.');

        userDialogOpen.value = false;
        resetForm();
        await loadUsers();
        toast.success(isEditing ? 'Usuario actualizado.' : 'Usuario creado.');
    } catch (err) {
        toast.error(
            err instanceof Error ? err.message : 'Error al guardar usuario.',
        );
    } finally {
        submitting.value = false;
    }
};

const confirmDelete = async (): Promise<void> => {
    if (!isAdmin.value || !userToDelete.value || deleting.value) return;
    deleting.value = true;

    try {
        const response = await apiFetch(
            `/api/v1/usuarios/${userToDelete.value.id}`,
            { method: 'DELETE' },
        );
        if (!response.ok) throw new Error('No se pudo eliminar el usuario.');

        users.value = users.value.filter(
            (user) => user.id !== userToDelete.value?.id,
        );
        deleteDialogOpen.value = false;
        userToDelete.value = null;
        toast.success('Usuario eliminado.');
    } catch (err) {
        toast.error(
            err instanceof Error ? err.message : 'Error al eliminar usuario.',
        );
    } finally {
        deleting.value = false;
    }
};

const requestDelete = (user: UsuarioApi): void => {
    if (!isAdmin.value) return;
    userToDelete.value = user;
    deleteDialogOpen.value = true;
};

onMounted(() => {
    if (!canManageUsers.value) {
        error.value = 'Tu rol no tiene acceso a la gestión de usuarios.';
        loading.value = false;
        return;
    }
    void loadUsers();
});
</script>

<template>
    <Head title="Gestión de usuarios" />

    <div class="w-full space-y-6 p-4 sm:p-6 xl:p-8">
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p class="text-xs font-semibold text-blue-600 uppercase">
                    Administración
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1
                        class="text-2xl font-semibold text-slate-900 sm:text-3xl"
                    >
                        Gestión de usuarios
                    </h1>
                    <Badge
                        variant="outline"
                        class="border-slate-200 bg-white text-slate-700"
                    >
                        {{ users.length }} cuentas
                    </Badge>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    {{
                        isAdmin
                            ? 'Administra los perfiles y roles del sistema.'
                            : 'Consulta y actualiza perfiles de usuarios.'
                    }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    :disabled="refreshing"
                    @click="loadUsers(true)"
                >
                    <RefreshCw
                        :class="['h-4 w-4', refreshing && 'animate-spin']"
                    />
                    Actualizar
                </Button>
                <Button @click="openCreateDialog">
                    <UserPlus class="h-4 w-4" />
                    Crear usuario
                </Button>
            </div>
        </header>

        <div
            v-if="error"
            class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            role="alert"
        >
            {{ error }}
        </div>

        <Card
            class="w-full rounded-lg border border-slate-200 bg-white shadow-sm"
        >
            <CardHeader class="pb-4">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-2">
                        <Users class="h-5 w-5 text-blue-600" />
                        <CardTitle class="text-lg text-slate-900"
                            >Cuentas registradas</CardTitle
                        >
                    </div>
                    <div class="relative w-full sm:max-w-sm">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            v-model="search"
                            class="h-10 border-slate-200 pl-9"
                            placeholder="Buscar nombre, correo o rol"
                            aria-label="Buscar usuarios"
                        />
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="loading" class="space-y-3">
                    <Skeleton
                        v-for="index in 5"
                        :key="index"
                        class="h-14 w-full rounded-md"
                    />
                </div>
                <div
                    v-else-if="filteredUsers.length === 0"
                    class="flex min-h-52 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center"
                >
                    <ShieldCheck class="h-8 w-8 text-slate-400" />
                    <p class="mt-3 font-medium text-slate-800">
                        No se encontraron usuarios
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        Prueba otra búsqueda o crea una cuenta nueva.
                    </p>
                </div>
                <div v-else>
                    <div class="space-y-3 md:hidden">
                        <article
                            v-for="user in filteredUsers"
                            :key="user.id"
                            class="rounded-md border border-slate-200 bg-white p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2
                                        class="font-semibold break-words text-slate-900"
                                    >
                                        {{ user.name }}
                                    </h2>
                                    <p
                                        class="mt-1 text-sm break-all text-slate-600"
                                    >
                                        {{ user.email }}
                                    </p>
                                </div>
                                <Badge
                                    :class="[
                                        'shrink-0 border',
                                        roleTone(user.rol),
                                    ]"
                                    >{{ user.rol }}</Badge
                                >
                            </div>
                            <div
                                class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3"
                            >
                                <time class="text-xs text-slate-500"
                                    >Registro
                                    {{ formatDate(user.created_at) }}</time
                                >
                                <div class="flex gap-1">
                                    <Button
                                        v-if="isAdmin || user.rol === 'usuario'"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        :aria-label="`Editar ${user.name}`"
                                        @click="openEditDialog(user)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="isAdmin"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-red-600 hover:bg-red-50"
                                        :aria-label="`Eliminar ${user.name}`"
                                        @click="requestDelete(user)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table
                            class="w-full min-w-[760px] table-auto text-left text-sm"
                        >
                            <thead
                                class="border-y border-slate-200 bg-slate-50 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-medium"
                                    >
                                        Nombre
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-medium"
                                    >
                                        Email
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-medium"
                                    >
                                        Rol
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-medium"
                                    >
                                        Fecha de registro
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right font-medium"
                                    >
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="user in filteredUsers"
                                    :key="user.id"
                                    class="hover:bg-slate-50/70"
                                >
                                    <td
                                        class="px-4 py-3 font-medium text-slate-900"
                                    >
                                        {{ user.name }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ user.email }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge
                                            :class="[
                                                'border',
                                                roleTone(user.rol),
                                            ]"
                                            >{{ user.rol }}</Badge
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3 whitespace-nowrap text-slate-500"
                                    >
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            v-if="
                                                isAdmin ||
                                                user.rol === 'usuario'
                                            "
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`Editar ${user.name}`"
                                            @click="openEditDialog(user)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            v-if="isAdmin"
                                            variant="ghost"
                                            size="icon"
                                            class="text-red-600 hover:bg-red-50"
                                            :aria-label="`Eliminar ${user.name}`"
                                            @click="requestDelete(user)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog :open="userDialogOpen" @update:open="userDialogOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="text-slate-900">{{
                        editingUser ? 'Editar usuario' : 'Crear usuario'
                    }}</DialogTitle>
                    <DialogDescription class="text-slate-600">
                        {{
                            editingUser
                                ? 'Actualiza los datos del perfil.'
                                : 'Completa los datos para registrar una cuenta.'
                        }}
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="saveUser">
                    <div class="space-y-2">
                        <Label for="user-name">Nombre</Label>
                        <Input
                            id="user-name"
                            v-model="form.name"
                            required
                            autocomplete="name"
                        />
                        <p
                            v-if="validationErrors.name"
                            class="text-sm text-red-600"
                        >
                            {{ validationErrors.name }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="user-email">Email</Label>
                        <Input
                            id="user-email"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                        />
                        <p
                            v-if="validationErrors.email"
                            class="text-sm text-red-600"
                        >
                            {{ validationErrors.email }}
                        </p>
                    </div>
                    <div v-if="!editingUser || isAdmin" class="space-y-2">
                        <Label for="user-password">{{
                            editingUser
                                ? 'Nueva contraseña (opcional)'
                                : 'Contraseña'
                        }}</Label>
                        <Input
                            id="user-password"
                            v-model="form.password"
                            type="password"
                            :required="!editingUser"
                            autocomplete="new-password"
                        />
                        <p
                            v-if="validationErrors.password"
                            class="text-sm text-red-600"
                        >
                            {{ validationErrors.password }}
                        </p>
                    </div>
                    <div v-if="isAdmin || !editingUser" class="space-y-2">
                        <Label for="user-role">Rol</Label>
                        <Select v-model="form.rol">
                            <SelectTrigger id="user-role" class="w-full"
                                ><SelectValue placeholder="Selecciona un rol"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="usuario">Usuario</SelectItem>
                                <SelectItem v-if="isAdmin" value="empleado"
                                    >Empleado</SelectItem
                                >
                                <SelectItem v-if="isAdmin" value="administrador"
                                    >Administrador</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <p
                            v-if="validationErrors.rol"
                            class="text-sm text-red-600"
                        >
                            {{ validationErrors.rol }}
                        </p>
                    </div>
                    <DialogFooter>
                        <DialogClose as-child
                            ><Button
                                type="button"
                                variant="outline"
                                :disabled="submitting"
                                >Cancelar</Button
                            ></DialogClose
                        >
                        <Button type="submit" :disabled="submitting">
                            <RefreshCw
                                v-if="submitting"
                                class="h-4 w-4 animate-spin"
                            />
                            {{
                                submitting
                                    ? 'Guardando…'
                                    : editingUser
                                      ? 'Guardar cambios'
                                      : 'Crear usuario'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="deleteDialogOpen"
            @update:open="deleteDialogOpen = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="text-slate-900"
                        >Eliminar usuario</DialogTitle
                    >
                    <DialogDescription class="text-slate-600">
                        Esta acción es permanente. ¿Eliminar a
                        <strong>{{ userToDelete?.name }}</strong
                        >?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose as-child
                        ><Button variant="outline" :disabled="deleting"
                            >Cancelar</Button
                        ></DialogClose
                    >
                    <Button
                        variant="destructive"
                        :disabled="deleting"
                        @click="confirmDelete"
                    >
                        <Trash2 class="h-4 w-4" />
                        {{
                            deleting
                                ? 'Eliminando…'
                                : 'Eliminar definitivamente'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
