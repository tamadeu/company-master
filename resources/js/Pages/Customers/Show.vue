<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowLeft, CalendarDays, ContactRound, MapPin, ReceiptText, Repeat2, ShoppingBag, Star, TrendingUp, Trophy } from '@lucide/vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface GameSummary { id: number; currentDate: string; dayNumber: number; victoryDays: number }
interface CompanySummary { id: number; name: string }
interface CustomerProfile { id: number; code: string; name: string; gender: string; age: number; birthDate: string; city: string; state: string; email: string | null; acquiredOn: string; lastPurchaseOn: string; status: string }
interface Analytics { purchaseCount: number; totalUnits: number; lifetimeValueCents: number; averageTicketCents: number; daysSinceLastPurchase: number; activeDays: number; purchasesPer30DaysBasisPoints: number; rankingPosition: number | null; segment: 'new' | 'recurring' | 'vip' }
interface ProductAnalytics { productId: number; productName: string; purchaseCount: number; units: number; revenueCents: number; revenueShareBasisPoints: number }
interface TimelineItem { date: string; purchaseCount: number; units: number; revenueCents: number }
interface Purchase { id: number; orderId: number; orderNumber: string; date: string; productName: string; quantity: number; revenueCents: number; unitPriceCents: number }

const props = defineProps<{ game: GameSummary; company: CompanySummary; customer: CustomerProfile; analytics: Analytics; products: ProductAnalytics[]; timeline: TimelineItem[]; purchases: Purchase[] }>();
const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string) => new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`));
const maxDailyRevenue = computed(() => Math.max(1, ...props.timeline.map((item) => item.revenueCents)));
const segmentLabel = computed(() => ({ new: 'Novo cliente', recurring: 'Cliente recorrente', vip: 'Cliente VIP' })[props.analytics.segment]);
</script>

<template>
    <Head :title="customer.name" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <Link :href="route('games.customers.index', game.id)" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#31506d] hover:text-[#1769aa]"><ArrowLeft :size="16" /> Voltar para clientes</Link>

            <section class="flex flex-col gap-5 rounded-md border border-[#dfe7ee] bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
                <div class="flex items-start gap-4"><span class="grid size-14 shrink-0 place-items-center rounded-md bg-[#dff7f1] text-[#008b76]"><ContactRound :size="28" /></span><div><div class="flex flex-wrap items-center gap-2"><h1 class="text-2xl font-bold text-[#102039]">{{ customer.name }}</h1><span class="rounded px-2 py-1 text-[11px] font-bold" :class="analytics.segment === 'vip' ? 'bg-[#fff3dd] text-[#a56500]' : analytics.segment === 'recurring' ? 'bg-[#e4f0fb] text-[#1769aa]' : 'bg-[#dff7f1] text-[#087c68]'">{{ segmentLabel }}</span></div><div class="mt-1 text-sm text-[#6b7f93]">{{ customer.code }} · {{ customer.age }} anos · {{ customer.gender }}</div><div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#718599]"><span class="inline-flex items-center gap-1"><MapPin :size="13" /> {{ customer.city }}/{{ customer.state }}</span><span v-if="customer.email">{{ customer.email }}</span></div></div></div>
                <div class="text-left md:text-right"><div class="text-xs text-[#718599]">Cliente desde</div><div class="mt-1 font-bold text-[#263e56]">{{ formatDate(customer.acquiredOn) }}</div><div class="mt-1 text-xs text-[#718599]">Última compra {{ formatDate(customer.lastPurchaseOn) }}</div></div>
            </section>

            <section class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-6" aria-label="Analytics do cliente">
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><ShoppingBag :size="19" class="text-[#1769aa]" /><div class="mt-3 text-xs text-[#64798d]">Compras</div><div class="text-xl font-bold text-[#102039]">{{ analytics.purchaseCount }}</div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><ReceiptText :size="19" class="text-[#087c68]" /><div class="mt-3 text-xs text-[#64798d]">Valor vitalício</div><div class="text-xl font-bold text-[#102039]">{{ formatMoney(analytics.lifetimeValueCents) }}</div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><TrendingUp :size="19" class="text-[#6650a4]" /><div class="mt-3 text-xs text-[#64798d]">Ticket médio</div><div class="text-xl font-bold text-[#102039]">{{ formatMoney(analytics.averageTicketCents) }}</div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><Repeat2 :size="19" class="text-[#b56b00]" /><div class="mt-3 text-xs text-[#64798d]">Compras / 30 dias</div><div class="text-xl font-bold text-[#102039]">{{ (analytics.purchasesPer30DaysBasisPoints / 10000).toFixed(2) }}</div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><CalendarDays :size="19" class="text-[#d65737]" /><div class="mt-3 text-xs text-[#64798d]">Recência</div><div class="text-xl font-bold text-[#102039]">{{ analytics.daysSinceLastPurchase }}d</div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><Trophy :size="19" class="text-[#008b76]" /><div class="mt-3 text-xs text-[#64798d]">Ranking de valor</div><div class="text-xl font-bold text-[#102039]">#{{ analytics.rankingPosition ?? '—' }}</div></article>
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-[1fr_1fr]">
                <div class="rounded-md border border-[#dfe7ee] bg-white shadow-sm"><div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><Star :size="19" /> Produtos preferidos</div><span class="text-xs text-[#75899c]">Participação no valor</span></div><div class="space-y-4 p-5"><div v-for="product in products" :key="product.productId"><div class="mb-1 flex items-center justify-between gap-3 text-xs"><span class="font-semibold text-[#354d64]">{{ product.productName }} · {{ product.units }} un.</span><strong class="text-[#263e56]">{{ formatMoney(product.revenueCents) }}</strong></div><div class="h-2 overflow-hidden rounded-full bg-[#e8eef3]"><div class="h-full rounded-full bg-[#19b6a5]" :style="{ width: `${product.revenueShareBasisPoints / 100}%` }"></div></div></div></div></div>
                <div class="rounded-md border border-[#dfe7ee] bg-white shadow-sm"><div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><TrendingUp :size="19" /> Evolução de compras</div><span class="text-xs text-[#75899c]">{{ timeline.length }} dia(s)</span></div><div class="space-y-3 p-5"><div v-for="item in timeline" :key="item.date" class="grid grid-cols-[5rem_1fr_auto] items-center gap-3"><span class="text-xs text-[#6e8296]">{{ formatDate(item.date) }}</span><div class="h-2 overflow-hidden rounded-full bg-[#e8eef3]"><div class="h-full rounded-full bg-[#1769aa]" :style="{ width: `${Math.max(3, Math.round((item.revenueCents * 100) / maxDailyRevenue))}%` }"></div></div><strong class="text-xs text-[#263e56]">{{ formatMoney(item.revenueCents) }}</strong></div></div></div>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm"><div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><ReceiptText :size="19" /> Histórico de compras</div><span class="text-xs text-[#75899c]">{{ purchases.length }} item(ns)</span></div><div class="overflow-x-auto"><table class="w-full min-w-[860px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">ID da venda</th><th class="px-4 py-3">Data</th><th class="px-4 py-3">Produto</th><th class="px-4 py-3 text-right">Quantidade</th><th class="px-4 py-3 text-right">Preço unitário</th><th class="px-5 py-3 text-right">Total</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="purchase in purchases" :key="purchase.id"><td class="px-5 py-3.5 font-mono text-xs font-bold text-[#17638d]">{{ purchase.orderNumber }}</td><td class="px-4 py-3.5 text-sm text-[#61758a]">{{ formatDate(purchase.date) }}</td><td class="px-4 py-3.5 text-sm font-semibold text-[#263e56]">{{ purchase.productName }}</td><td class="px-4 py-3.5 text-right text-sm">{{ purchase.quantity }}</td><td class="px-4 py-3.5 text-right text-sm text-[#61758a]">{{ formatMoney(purchase.unitPriceCents) }}</td><td class="px-5 py-3.5 text-right text-sm font-bold text-[#087c68]">{{ formatMoney(purchase.revenueCents) }}</td></tr></tbody></table></div></section>
        </div>
    </AuthenticatedLayout>
</template>