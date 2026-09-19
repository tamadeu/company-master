<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Boxes, CircleDollarSign, History, Package, PackageOpen } from '@lucide/vue';
import { Head } from '@inertiajs/vue3';

interface GameSummary {
    id: number;
    currentDate: string;
    dayNumber: number;
    victoryDays: number;
}

interface CompanySummary {
    id: number;
    name: string;
}

interface Balance {
    productId: number;
    productName: string;
    sku: string;
    quantity: number;
    averageCostCents: number;
    totalValueCents: number;
}

interface Movement {
    id: number;
    productName: string;
    type: string;
    quantity: number;
    unitCostCents: number;
    totalCostCents: number;
    gameDate: string;
    referenceId: number;
}

defineProps<{
    game: GameSummary;
    company: CompanySummary;
    summary: { totalUnits: number; totalValueCents: number; productsInStock: number };
    balances: Balance[];
    movements: Movement[];
}>();

const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string) => new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`));
</script>

<template>
    <Head title="Estoque" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6"><h1 class="text-3xl font-bold text-[#102039] lg:text-4xl">Estoque</h1><p class="mt-1 text-sm text-[#657a90]">Posição atual e trilha imutável das entradas de produtos.</p></div>

            <section class="grid gap-3 sm:grid-cols-3" aria-label="Resumo do estoque">
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#dff7f1] text-[#008b76]"><Boxes :size="22" /></span><div><div class="text-xs text-[#64798d]">Unidades em estoque</div><div class="text-2xl font-bold text-[#102039]">{{ summary.totalUnits }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#e4f0fb] text-[#1769aa]"><Package :size="22" /></span><div><div class="text-xs text-[#64798d]">Produtos com saldo</div><div class="text-2xl font-bold text-[#102039]">{{ summary.productsInStock }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#fff0e9] text-[#e6653f]"><CircleDollarSign :size="22" /></span><div><div class="text-xs text-[#64798d]">Valor a custo</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(summary.totalValueCents) }}</div></div></div></article>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><PackageOpen :size="19" /> Posição por produto</div><span class="text-xs text-[#75899c]">Custo médio ponderado</span></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Produto</th><th class="px-4 py-3 text-right">Quantidade</th><th class="px-4 py-3 text-right">Custo médio</th><th class="px-5 py-3 text-right">Valor total</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="balance in balances" :key="balance.productId"><td class="px-5 py-3.5"><div class="text-sm font-semibold text-[#263e56]">{{ balance.productName }}</div><div class="text-[11px] text-[#8a99a8]">{{ balance.sku }}</div></td><td class="px-4 py-3.5 text-right"><span class="rounded px-2 py-1 text-xs font-bold" :class="balance.quantity > 0 ? 'bg-[#dff7f1] text-[#087c68]' : 'bg-[#fff0e9] text-[#d65737]'">{{ balance.quantity }} un.</span></td><td class="px-4 py-3.5 text-right text-sm text-[#435a71]">{{ formatMoney(balance.averageCostCents) }}</td><td class="px-5 py-3.5 text-right text-sm font-bold text-[#263e56]">{{ formatMoney(balance.totalValueCents) }}</td></tr></tbody></table></div>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><History :size="19" /> Movimentações</div><span class="text-xs text-[#75899c]">{{ movements.length }} registro(s)</span></div>
                <div v-if="movements.length === 0" class="grid min-h-44 place-items-center px-6 py-10 text-center"><div><History :size="30" class="mx-auto text-[#9babb8]" /><div class="mt-3 text-sm font-bold text-[#354d64]">Nenhuma movimentação</div><p class="mt-1 text-xs text-[#7a8c9d]">As entradas aparecerão quando os pedidos forem recebidos.</p></div></div>
                <div v-else class="overflow-x-auto"><table class="w-full min-w-[720px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Data</th><th class="px-4 py-3">Produto</th><th class="px-4 py-3">Origem</th><th class="px-4 py-3 text-right">Quantidade</th><th class="px-5 py-3 text-right">Custo total</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="movement in movements" :key="movement.id"><td class="px-5 py-3.5 text-sm text-[#61758a]">{{ formatDate(movement.gameDate) }}</td><td class="px-4 py-3.5 text-sm font-semibold text-[#263e56]">{{ movement.productName }}</td><td class="px-4 py-3.5 text-sm text-[#61758a]">Pedido #{{ movement.referenceId }}</td><td class="px-4 py-3.5 text-right text-sm font-bold text-[#16836f]">+{{ movement.quantity }}</td><td class="px-5 py-3.5 text-right text-sm font-bold text-[#263e56]">{{ formatMoney(movement.totalCostCents) }}</td></tr></tbody></table></div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>