<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowRight, CircleDollarSign, ContactRound, Gauge, PackageOpen, ReceiptText, ShoppingBag, TrendingUp, Users } from '@lucide/vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface GameSummary { id: number; currentDate: string; dayNumber: number; victoryDays: number }
interface CompanySummary { id: number; name: string }
interface SalesRules { dailyCapacityUnits: number; commercialEmployees: number; ownerBaseCapacityUnits: number; productivityMinBasisPoints: number; productivityMaxBasisPoints: number; latestUnmetDemandUnits: number }
interface CustomerOrder { id: number; number: string; date: string; status: string; customer: { id: number; name: string; code: string }; skuCount: number; totalQuantity: number; revenueCents: number; detailUrl: string }
interface PageLink { url: string | null; label: string; active: boolean }
interface PaginatedOrders { data: CustomerOrder[]; links: PageLink[]; from: number | null; to: number | null; total: number }
interface ProductPerformance { productId: number; productName: string; unitsSold: number; revenueCents: number; grossProfitCents: number }

const props = defineProps<{
    game: GameSummary;
    company: CompanySummary;
    salesRules: SalesRules;
    summary: { revenueCents: number; cogsCents: number; unitsSold: number; processedSales: number; customerPurchases: number };
    orders: PaginatedOrders;
    productPerformance: ProductPerformance[];
}>();

const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string) => new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`));
const grossProfitCents = computed(() => props.summary.revenueCents - props.summary.cogsCents);
const maxProductRevenue = computed(() => Math.max(1, ...props.productPerformance.map((item) => item.revenueCents)));
const cleanLabel = (label: string) => ({
    'pagination.previous': 'Anterior',
    'pagination.next': 'Próxima',
}[label] ?? label.replace('&laquo;', '').replace('&raquo;', '').trim());
</script>

<template>
    <Head title="Vendas" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6"><h1 class="text-3xl font-bold text-[#102039] lg:text-4xl">Vendas</h1><p class="mt-1 text-sm text-[#657a90]">Consulte os registros processados, clientes atendidos e desempenho comercial.</p></div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Resumo de vendas">
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><CircleDollarSign :size="20" class="text-[#008b76]" /><div class="mt-3 text-xs text-[#64798d]">Faturamento</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(summary.revenueCents) }}</div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><TrendingUp :size="20" class="text-[#1769aa]" /><div class="mt-3 text-xs text-[#64798d]">Lucro bruto</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(grossProfitCents) }}</div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><ShoppingBag :size="20" class="text-[#e6653f]" /><div class="mt-3 text-xs text-[#64798d]">Unidades vendidas</div><div class="text-2xl font-bold text-[#102039]">{{ summary.unitsSold }}</div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><ContactRound :size="20" class="text-[#6650a4]" /><div class="mt-3 text-xs text-[#64798d]">Pedidos de clientes</div><div class="text-2xl font-bold text-[#102039]">{{ summary.customerPurchases }}</div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><Gauge :size="20" class="text-[#27658e]" /><div class="mt-3 text-xs text-[#64798d]">Capacidade hoje</div><div class="text-2xl font-bold text-[#102039]">{{ salesRules.dailyCapacityUnits }} un.</div><div class="text-[11px] text-[#8393a3]">{{ salesRules.commercialEmployees }} pessoa(s) no Comercial</div></article>
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-[1.35fr_0.65fr]">
                <div class="overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><ReceiptText :size="19" /> Vendas por pedido</div><span class="text-xs text-[#75899c]">{{ orders.total }} venda(s) identificada(s)</span></div>
                    <div v-if="orders.data.length" class="overflow-x-auto">
                        <table class="w-full min-w-[940px] text-left">
                            <thead class="bg-[#f8fafc] text-[10px] uppercase text-[#8293a4]"><tr><th class="px-5 py-3">ID da venda</th><th class="px-4 py-3">Data</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3 text-right">SKUs</th><th class="px-4 py-3 text-right">Unidades</th><th class="px-4 py-3 text-right">Total</th><th class="px-5 py-3 text-right">Carrinho</th></tr></thead>
                            <tbody class="divide-y divide-[#edf1f4]"><tr v-for="order in orders.data" :key="order.id" class="hover:bg-[#f8fbfc]"><td class="px-5 py-4 font-mono text-xs font-bold text-[#17638d]">{{ order.number }}</td><td class="px-4 py-4 text-sm text-[#526a80]">{{ formatDate(order.date) }}</td><td class="px-4 py-4"><div class="text-sm font-semibold text-[#263e56]">{{ order.customer.name }}</div><div class="text-[11px] text-[#8a99a8]">{{ order.customer.code }}</div></td><td class="px-4 py-4 text-right text-sm font-semibold">{{ order.skuCount }}</td><td class="px-4 py-4 text-right text-sm">{{ order.totalQuantity }}</td><td class="px-4 py-4 text-right text-sm font-bold text-[#087c68]">{{ formatMoney(order.revenueCents) }}</td><td class="px-5 py-4"><Link :href="order.detailUrl" class="ml-auto grid size-9 place-items-center rounded-md border border-[#d7e2ea] text-[#17638d] hover:bg-[#edf6f8]" :aria-label="`Abrir pedido ${order.number}`"><ArrowRight :size="17" /></Link></td></tr></tbody>
                        </table>
                    </div>
                    <div v-else class="grid min-h-64 place-items-center p-8 text-center"><div><ReceiptText :size="34" class="mx-auto text-[#9babb8]" /><h2 class="mt-3 text-sm font-bold text-[#354d64]">Nenhuma venda processada</h2><p class="mt-1 text-xs text-[#7a8c9d]">Vendas aparecerão aqui depois que um dia for avançado com estoque disponível.</p></div></div>
                    <div v-if="orders.links.length > 3" class="flex flex-col gap-3 border-t border-[#e6edf2] px-5 py-4 text-xs text-[#718499] sm:flex-row sm:items-center sm:justify-between"><span>{{ orders.from ?? 0 }}–{{ orders.to ?? 0 }} de {{ orders.total }}</span><div class="flex flex-wrap gap-1"><Link v-for="link in orders.links" :key="link.label" :href="link.url ?? '#'" class="grid min-h-8 min-w-8 place-items-center rounded px-2 font-semibold" :class="link.active ? 'bg-[#173f67] text-white' : link.url ? 'border border-[#d7e2ea] text-[#526a80]' : 'text-[#b0bdc8]'" preserve-scroll>{{ cleanLabel(link.label) }}</Link></div></div>
                </div>

                <div class="h-fit rounded-md border border-[#dfe7ee] bg-white shadow-sm"><div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><PackageOpen :size="19" /> Desempenho por produto</div></div><div v-if="productPerformance.length" class="space-y-4 p-5"><div v-for="item in productPerformance" :key="item.productId"><div class="mb-1 flex justify-between gap-3 text-xs"><span class="font-semibold text-[#354d64]">{{ item.productName }} · {{ item.unitsSold }} un.</span><strong>{{ formatMoney(item.revenueCents) }}</strong></div><div class="h-2 overflow-hidden rounded-full bg-[#e8eef3]"><div class="h-full rounded-full bg-[#19b6a5]" :style="{ width: `${Math.max(2, Math.round((item.revenueCents * 100) / maxProductRevenue))}%` }"></div></div></div></div><div v-else class="p-6 text-center text-sm text-[#7a8c9d]">Sem desempenho registrado.</div></div>
            </section>

            <section class="mt-4 rounded-md border border-[#dfe7ee] bg-white p-5 shadow-sm"><div class="flex items-start gap-3"><Users :size="21" class="mt-0.5 shrink-0 text-[#27658e]" /><div><h2 class="text-sm font-bold text-[#19324d]">Regra operacional</h2><p class="mt-1 text-sm leading-6 text-[#6b7f93]">As vendas são automáticas ao avançar o dia e respeitam demanda, estoque e capacidade comercial. Configuração de preço fica em <strong>Estoque e produtos</strong>. O gestor cobre {{ salesRules.ownerBaseCapacityUnits }} un./dia e a equipe Comercial amplia o limite.</p><p v-if="salesRules.latestUnmetDemandUnits" class="mt-1 text-xs font-semibold text-[#c45027]">Último dia: {{ salesRules.latestUnmetDemandUnits }} unidade(s) de demanda não atendida.</p></div></div></section>
        </div>
    </AuthenticatedLayout>
</template>