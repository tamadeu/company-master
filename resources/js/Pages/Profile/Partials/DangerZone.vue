<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { RotateCcw, TriangleAlert } from '@lucide/vue';
import DeleteUserForm from './DeleteUserForm.vue';
</script>

<template>
    <section>
        <header class="flex items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-md bg-[#fff0ed] text-[#c44032]">
                <TriangleAlert :size="21" />
            </span>
            <div>
                <h2 class="text-lg font-bold text-[#8f2f27]">Zona de perigo</h2>
                <p class="mt-1 text-sm text-[#765f5d]">Ações que iniciam um novo ciclo ou removem permanentemente seus dados.</p>
            </div>
        </header>

        <div class="mt-6 divide-y divide-[#f0d9d5] border-y border-[#f0d9d5]">
            <div class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="max-w-2xl">
                    <h3 class="text-sm font-bold text-[#263e56]">Iniciar nova partida do zero</h3>
                    <p class="mt-1 text-sm leading-6 text-[#657a90]">Configure outra empresa com caixa, estoque e histórico reiniciados. Suas partidas anteriores continuarão disponíveis.</p>
                </div>
                <Link :href="route('dashboard', { new: 1 })" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-md bg-[#ef654f] px-4 text-xs font-bold uppercase text-white transition hover:bg-[#d95340] focus:outline-none focus:ring-2 focus:ring-[#ef654f] focus:ring-offset-2">
                    <RotateCcw :size="16" /> Iniciar nova partida
                </Link>
            </div>

            <DeleteUserForm />
        </div>
    </section>
</template>