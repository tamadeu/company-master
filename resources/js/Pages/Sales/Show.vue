<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowLeft, CalendarDays, CheckCircle2, CircleDollarSign, ContactRound, MapPin, PackageOpen, ShoppingBag } from '@lucide/vue';
import { Head, Link } from '@inertiajs/vue3';

interface GameSummary { id: number; currentDate: string; dayNumber: number; victoryDays: number }
interface CompanySummary { id: number; name: string }
interface OrderItem { productId: number; productName: string; sku: string; quantity: number; unitPriceCents: number; revenueCents: number }
interface CustomerOrder {
    id: number;
    number: string;
    date: string;
    status: string;
    totalQuantity: number;
    revenueCents: number;
    skuCount: number;
    createdAt: string;
    customer: { id: number; name: string; code: string; email: string | null; city: string; state: string };
    items: OrderItem[];
}

defineProps<{ game: GameSummary; company: CompanySummary; order: CustomerOrder }>();

const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string) => new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`));
const formatTimestamp = (value: string) => new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
</script>

<template>
    <Head :title="order.number" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1300px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <Link :href="route('games.products.index', game.id)" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#31506d] hover:text-[#1769aa]"><ArrowLeft :size="16" /> Voltar para vendas</Link>

            <header class="flex flex-col gap-4 border-b border-[#d7e2ea] pb-6 md:flex-row md:items-end md:justify-between">
                <div><div class="flex items-center gap-2 text-xs font-bold uppercase text-[#087c68]"><CheckCircle2 :size="16" /> Pedido concluído</div><h1 class="mt-2 font-mono text-2xl font-bold text-[#102039] sm:text-3xl">{{ order.number }}</h1><p class="mt-1 text-sm text-[#657a90]">Venda processada em {{ formatTimestamp(order.createdAt) }}</p></div>
                <div class="flex items-center gap-2 text-sm font-semibold text-[#526a80]"><CalendarDays :size="17" /> Data do jogo: {{ formatDate(order.date) }}</div>
            </header>

            <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo do pedido">
                <article class="border-l-4 border-[#18a995] bg-white p-5 shadow-sm"><CircleDollarSign :size="20" class="text-[#087c68]" /><div class="mt-4 text-xs text-[#718499]">Total do pedido</div><div class="mt-1 text-2xl font-bold text-[#102039]">{{ formatMoney(order.revenueCents) }}</div></article>
                <article class="border-l-4 border-[#e6653f] bg-white p-5 shadow-sm"><ShoppingBag :size="20" class="text-[#d65737]" /><div class="mt-4 text-xs text-[#718499]">Unidades</div><div class="mt-1 text-2xl font-bold text-[#102039]">{{ order.totalQuantity }}</div></article>
                <article class="border-l-4 border-[#1769aa] bg-white p-5 shadow-sm"><PackageOpen :size="20" class="text-[#1769aa]" /><div class="mt-4 text-xs text-[#718499]">SKUs distintos</div><div class="mt-1 text-2xl font-bold text-[#102039]">{{ order.skuCount }}</div></article>
                <article class="border-l-4 border-[#6650a4] bg-white p-5 shadow-sm"><ContactRound :size="20" class="text-[#6650a4]" /><div class="mt-4 text-xs text-[#718499]">Cliente</div><div class="mt-1 truncate text-base font-bold text-[#102039]">{{ order.customer.name }}</div></article>
            </section>

            <section class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div class="overflow-hidden border border-[#dce5ec] bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><PackageOpen :size="19" /> Produtos do carrinho</div><span class="text-xs text-[#718499]">{{ order.items.length }} item(ns)</span></div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[700px] text-left"><thead class="bg-[#f8fafc] text-[10px] uppercase text-[#8293a4]"><tr><th class="px-5 py-3">Produto</th><th class="px-4 py-3">SKU</th><th class="px-4 py-3 text-right">Quantidade</th><th class="px-4 py-3 text-right">Preço unitário</th><th class="px-5 py-3 text-right">Subtotal</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="item in order.items" :key="item.productId"><td class="px-5 py-4 text-sm font-semibold text-[#263e56]">{{ item.productName }}</td><td class="px-4 py-4 font-mono text-xs text-[#718499]">{{ item.sku }}</td><td class="px-4 py-4 text-right text-sm">{{ item.quantity }}</td><td class="px-4 py-4 text-right text-sm text-[#526a80]">{{ formatMoney(item.unitPriceCents) }}</td><td class="px-5 py-4 text-right text-sm font-bold text-[#087c68]">{{ formatMoney(item.revenueCents) }}</td></tr></tbody><tfoot class="border-t-2 border-[#dce5ec] bg-[#fbfcfd]"><tr><td colspan="4" class="px-5 py-4 text-right text-sm font-bold text-[#526a80]">Total</td><td class="px-5 py-4 text-right text-lg font-bold text-[#087c68]">{{ formatMoney(order.revenueCents) }}</td></tr></tfoot></table></div>
                </div>

                <aside class="h-fit border border-[#dce5ec] bg-white p-5 shadow-sm"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><ContactRound :size="19" /> Dados do cliente</div><div class="mt-5 text-base font-bold text-[#263e56]">{{ order.customer.name }}</div><div class="mt-1 font-mono text-xs text-[#718499]">{{ order.customer.code }}</div><div class="mt-4 flex items-center gap-1 text-sm text-[#526a80]"><MapPin :size="15" /> {{ order.customer.city }}/{{ order.customer.state }}</div><div v-if="order.customer.email" class="mt-2 break-all text-xs text-[#718499]">{{ order.customer.email }}</div><Link :href="route('games.customers.show', [game.id, order.customer.id])" class="mt-5 inline-flex h-10 w-full items-center justify-center rounded-md border border-[#cbd8e2] text-sm font-bold text-[#17638d] hover:bg-[#f1f7fa]">Ver perfil do cliente</Link></aside>
            </section>
        </div>
    </AuthenticatedLayout>
</template>