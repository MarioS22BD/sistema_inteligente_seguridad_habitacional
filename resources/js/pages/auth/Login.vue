<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck, UserRound, LockKeyhole } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
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

    <div class="flex min-h-screen items-center justify-center bg-slate-950 p-4 text-slate-100">
        <div class="w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-slate-950/60">
            <div class="grid lg:grid-cols-2">
                <div class="flex flex-col justify-center bg-slate-950 px-8 py-10 lg:px-12">
                    <div class="mb-8 flex items-center gap-3">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-600/15 ring-1 ring-blue-500/50">
                            <ShieldCheck class="h-9 w-9 text-blue-400" />
                        </div>
                        <div>
                            <div class="text-2xl font-semibold text-white">Sistema de seguridad</div>
                            <div class="text-sm text-slate-400">Monitoreo en tiempo real</div>
                        </div>
                    </div>

                    <div class="mb-8 text-center">
                        <h1 class="text-3xl font-semibold text-white">Inicio de sesión</h1>
                        <p class="mt-2 text-sm text-slate-400">Ingresa tus credenciales para acceder al sistema.</p>
                    </div>

                    <div v-if="status" class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-center text-sm font-medium text-emerald-300">
                        {{ status }}
                    </div>

                    <PasskeyVerify />

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="space-y-5"
                    >
                        <div class="space-y-2">
                            <Label for="email" class="flex items-center gap-2 text-sm text-slate-200">
                                <UserRound class="h-4 w-4 text-blue-400" />
                                Usuario
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                v-focus
                                :tabindex="1"
                                autocomplete="email"
                                placeholder="usuario@ejemplo.com"
                                class="h-11 border-slate-700 bg-slate-900 text-white placeholder:text-slate-500"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label for="password" class="flex items-center gap-2 text-sm text-slate-200">
                                    <LockKeyhole class="h-4 w-4 text-blue-400" />
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
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="h-11 border-slate-700 bg-slate-900 text-white placeholder:text-slate-500"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <Label for="remember" class="flex items-center gap-3 text-sm text-slate-300">
                                <Checkbox id="remember" name="remember" :tabindex="3" class="border-slate-600 data-[state=checked]:bg-blue-600" />
                                <span>Recordarme</span>
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            class="mt-6 h-11 w-full bg-blue-600 text-white hover:bg-blue-500"
                            :tabindex="4"
                            :disabled="processing"
                            data-test="login-button"
                        >
                            <Spinner v-if="processing" />
                            Entrar
                        </Button>
                    </Form>
                </div>

                <div class="flex items-center justify-center bg-slate-800/70 p-8">
                    <div class="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900/80 p-6 shadow-inner shadow-slate-950/50">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/15 text-blue-400 ring-1 ring-blue-500/40">
                                <ShieldCheck class="h-6 w-6" />
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-white">Panel de seguridad</div>
                                <div class="text-xs text-slate-400">Control central del sistema</div>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                                <div class="text-xs uppercase tracking-wide text-emerald-300">Sistema</div>
                                <div class="mt-2 text-2xl font-semibold text-white">Activado</div>
                            </div>
                            <div class="rounded-xl border border-sky-500/30 bg-sky-500/10 p-4">
                                <div class="text-xs uppercase tracking-wide text-sky-300">Puerta</div>
                                <div class="mt-2 text-2xl font-semibold text-white">Cerrada</div>
                            </div>
                            <div class="rounded-xl border border-violet-500/30 bg-violet-500/10 p-4">
                                <div class="text-xs uppercase tracking-wide text-violet-300">Movimiento</div>
                                <div class="mt-2 text-2xl font-semibold text-white">No detectado</div>
                            </div>
                            <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                                <div class="text-xs uppercase tracking-wide text-amber-300">Estado</div>
                                <div class="mt-2 text-2xl font-semibold text-white">Normal</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
