<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    BellRing,
    Lock,
    Mail,
    ShieldCheck,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Sistema de seguridad',
        description: 'Ingrese sus credenciales para acceder al panel',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Inicio de sesión" />

    <main
        class="fixed inset-0 z-50 flex min-h-screen w-full flex-col overflow-y-auto bg-slate-950 text-white md:flex-row"
    >
        <section
            class="flex w-full flex-1 items-center justify-center bg-slate-950 px-5 py-10 sm:px-8 md:w-1/2 md:px-10 lg:w-5/12 lg:px-14 xl:px-20"
        >
            <div class="w-full max-w-xl">
                <div class="mb-10 flex items-center gap-3 md:hidden">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl border border-blue-400/30 bg-blue-400/10"
                    >
                        <ShieldCheck class="h-6 w-6 text-blue-300" />
                    </div>
                    <div>
                        <div class="font-semibold text-white">
                            Centro de Control
                        </div>
                        <div class="text-xs text-slate-400">
                            Seguridad habitacional
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <Badge
                        variant="outline"
                        class="mb-5 border-blue-400/30 bg-blue-400/10 text-blue-200"
                    >
                        Acceso seguro
                    </Badge>
                    <h1 class="text-3xl font-semibold text-white sm:text-4xl">
                        Iniciar sesión
                    </h1>
                    <p
                        class="mt-3 max-w-lg text-sm leading-6 text-slate-400 sm:text-base"
                    >
                        Ingresa tus credenciales para acceder al centro de
                        monitoreo y gestión de seguridad.
                    </p>
                </div>

                <div
                    v-if="status"
                    class="mb-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-300"
                >
                    {{ status }}
                </div>

                <PasskeyVerify />

                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <div class="space-y-2">
                        <Label
                            for="email"
                            class="flex items-center gap-2 text-sm text-slate-200"
                        >
                            <Mail class="h-4 w-4 text-blue-300" />
                            Correo electrónico
                        </Label>
                        <div class="relative">
                            <Mail
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-500"
                            />
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                v-focus
                                :tabindex="1"
                                autocomplete="email"
                                placeholder="usuario@ejemplo.com"
                                class="h-12 border-slate-700 bg-slate-900 pl-10 text-white placeholder:text-slate-500 focus-visible:border-blue-400"
                            />
                        </div>
                        <InputError :message="errors.email" />
                    </div>

                    <div class="space-y-2">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <Label
                                for="password"
                                class="flex items-center gap-2 text-sm text-slate-200"
                            >
                                <Lock class="h-4 w-4 text-blue-300" />
                                Contraseña
                            </Label>
                            <TextLink
                                v-if="canResetPassword"
                                :href="request()"
                                class="text-xs text-blue-300 hover:text-blue-200"
                                :tabindex="5"
                            >
                                ¿Olvidaste tu contraseña?
                            </TextLink>
                        </div>
                        <div class="relative">
                            <Lock
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-500"
                            />
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="h-12 border-slate-700 bg-slate-900 pl-10 text-white placeholder:text-slate-500 focus-visible:border-blue-400"
                            />
                        </div>
                        <InputError :message="errors.password" />
                    </div>

                    <Label
                        for="remember"
                        class="flex w-fit items-center gap-3 text-sm text-slate-300"
                    >
                        <Checkbox
                            id="remember"
                            name="remember"
                            :tabindex="3"
                            class="border-slate-600 data-[state=checked]:bg-blue-600 data-[state=checked]:text-white"
                        />
                        <span>Recordarme</span>
                    </Label>

                    <Button
                        type="submit"
                        class="h-12 w-full bg-blue-600 text-sm font-medium text-white transition hover:bg-blue-500"
                        :tabindex="4"
                        :disabled="processing"
                        data-test="login-button"
                    >
                        <Spinner v-if="processing" />
                        <span v-else class="inline-flex items-center gap-2">
                            Iniciar sesión
                            <ArrowRight class="h-4 w-4" />
                        </span>
                    </Button>
                </Form>

                <p class="mt-8 text-xs text-slate-500">
                    Acceso exclusivo para personal autorizado.
                </p>
            </div>
        </section>

        <aside
            class="relative hidden min-h-screen flex-col justify-between overflow-hidden border-l border-zinc-800 bg-zinc-900 p-10 md:flex md:w-1/2 lg:w-7/12 lg:p-14 xl:p-20"
        >
            <div
                class="pointer-events-none absolute inset-0 opacity-30"
                aria-hidden="true"
            >
                <div
                    class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_rgba(37,99,235,0.22),_transparent_55%)]"
                ></div>
                <div
                    class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.025)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.025)_1px,transparent_1px)] bg-[size:48px_48px]"
                ></div>
            </div>

            <header class="relative flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-xl border border-blue-400/30 bg-blue-400/10"
                    >
                        <ShieldCheck class="h-7 w-7 text-blue-300" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-blue-300 uppercase">
                            Sistema Inteligente
                        </p>
                        <h2 class="mt-1 text-lg font-semibold text-white">
                            Centro de Control de Seguridad
                        </h2>
                    </div>
                </div>
                <Badge
                    variant="outline"
                    class="hidden border-emerald-500/30 bg-emerald-500/10 text-emerald-300 xl:inline-flex"
                >
                    <Activity class="mr-1.5 h-3.5 w-3.5" />
                    Plataforma segura
                </Badge>
            </header>

            <div class="relative my-12 max-w-3xl xl:my-0">
                <p
                    class="mb-5 flex items-center gap-2 text-xs font-medium text-blue-300 uppercase"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-blue-400"></span>
                    Monitoreo y protección habitacional
                </p>
                <h3
                    class="max-w-2xl text-3xl leading-tight font-semibold text-white lg:text-4xl xl:text-5xl"
                >
                    Seguridad inteligente,
                    <span class="text-blue-300">en tiempo real.</span>
                </h3>
                <p
                    class="mt-5 max-w-xl text-sm leading-7 text-zinc-400 lg:text-base"
                >
                    Una visión centralizada del estado de sensores, accesos y
                    eventos para mantener cada habitación bajo supervisión.
                </p>

                <div class="mt-10 grid gap-3 sm:grid-cols-2 xl:gap-4">
                    <div
                        class="rounded-lg border border-zinc-700 bg-zinc-950/70 p-5"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div
                                class="flex items-center gap-2 text-xs font-medium text-zinc-400 uppercase"
                            >
                                <Activity class="h-4 w-4 text-emerald-400" />
                                Sistema
                            </div>
                            <span
                                class="h-2 w-2 rounded-full bg-emerald-400"
                            ></span>
                        </div>
                        <p class="mt-4 text-xl font-semibold text-white">
                            Monitoreo activo
                        </p>
                        <p class="mt-1 text-xs text-zinc-500">
                            Sensores conectados al centro de control
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-zinc-700 bg-zinc-950/70 p-5"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-medium text-zinc-400 uppercase"
                        >
                            <ShieldCheck class="h-4 w-4 text-sky-400" />
                            Protección
                        </div>
                        <p class="mt-4 text-xl font-semibold text-white">
                            Acceso supervisado
                        </p>
                        <p class="mt-1 text-xs text-zinc-500">
                            Registro de cambios y alertas relevantes
                        </p>
                    </div>
                </div>
            </div>

            <footer
                class="relative flex flex-col gap-2 border-t border-zinc-800 pt-5 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between"
            >
                <span>Sistema Inteligente de Seguridad para Habitaciones</span>
                <span class="inline-flex items-center gap-2">
                    <BellRing class="h-3.5 w-3.5 text-blue-300" />
                    Supervisión continua
                </span>
            </footer>
        </aside>
    </main>
</template>
