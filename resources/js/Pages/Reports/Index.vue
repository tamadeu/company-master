<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { BarChart3, CalendarDays, PackageSearch, TrendingUp } from '@lucide/vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface GameSummary { id: number; currentDate: string; dayNumber: number; victoryDays: number }
interface CompanySummary { id: number; name: string }
interface IncomeStatement { revenueCents: number; cogsCents: number; grossProfitCents: number; operatingExpensesCents: number; netProfitCents: number; grossMarginBasisPoints: number }
interface CashFlowItem { date: string; inflowsCents: number; outflowsCents: number; netCents: number; balanceCents: number }
interface DailySnapshot { date: string; revenueCents: number; unitsSold: number; cogsCents: number; grossProfitCents: number; expensesCents: number; cashChangeCents: number; cashBalanceCents: number }
interface ProductSale { productId: number; productName: string; unitsSold: number; revenueCents: number; cogsCents: number }

const props = defineProps<{ game: GameSummary; company: CompanySummary; incomeStatement: IncomeStatement; cashFlow: CashFlowItem[]; dailySnapshots: DailySnapshot[]; productSales: ProductSale[] }>();
const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string) => new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`));
const maxProductRevenue = computed(() => Math.max(1, ...props.productSales.map((item) => item.revenueCents)));
</script>

<template>
    <Head title="Relatórios" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6"><h1 class="text-3xl font-bold text-[#102039] lg:text-4xl">Relatórios</h1><p class="mt-1 text-sm text-[#657a90]">Histórico diário e desempenho acumulado da empresa.</p></div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="text-xs text-[#64798d]">Faturamento</div><div class="mt-1 text-2xl font-bold text-[#102039]">{{ formatMoney(incomeStatement.revenueCents) }}</div></article><article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="text-xs text-[#64798d]">Lucro bruto</div><div class="mt-1 text-2xl font-bold text-[#102039]">{{ formatMoney(incomeStatement.grossProfitCents) }}</div></article><article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="text-xs text-[#64798d]">Resultado líquido</div><div class="mt-1 text-2xl font-bold" :class="incomeStatement.netProfitCents >= 0 ? 'text-[#087c68]' : 'text-[#d65737]'">{{ formatMoney(incomeStatement.netProfitCents) }}</div></article><article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="text-xs text-[#64798d]">Dias processados</div><div class="mt-1 text-2xl font-bold text-[#102039]">{{ dailySnapshots.length }}</div></article></section>

            <section class="mt-4 grid gap-4 xl:grid-cols-[1fr_1fr]">
                <div class="rounded-md border border-[#dfe7ee] bg-white shadow-sm"><div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><PackageSearch :size="19" /> Vendas por produto</div><span class="text-xs text-[#75899c]">Acumulado</span></div><div v-if="productSales.length" class="space-y-4 p-5"><div v-for="item in productSales" :key="item.productId"><div class="mb-1 flex justify-between gap-3 text-xs"><span class="font-semibold text-[#354d64]">{{ item.productName }} · {{ item.unitsSold }} un.</span><strong class="text-[#263e56]">{{ formatMoney(item.revenueCents) }}</strong></div><div class="h-2 overflow-hidden rounded-full bg-[#e8eef3]"><div class="h-full rounded-full bg-[#19b6a5]" :style="{ width: `${Math.max(2, Math.round((item.revenueCents * 100) / maxProductRevenue))}%` }"></div></div></div></div><div v-else class="grid min-h-48 place-items-center p-6 text-center text-sm text-[#75899c]">As vendas por produto aparecerão após processar dias com estoque.</div></div>
                <div class="rounded-md border border-[#dfe7ee] bg-white shadow-sm"><div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><TrendingUp :size="19" /> Evolução do caixa</div><span class="text-xs text-[#75899c]">{{ cashFlow.length }} dia(s)</span></div><div class="space-y-3 p-5"><div v-for="item in cashFlow" :key="item.date" class="flex items-center justify-between gap-4 border-b border-[#edf1f4] pb-2 text-xs last:border-0"><span class="text-[#6e8296]">{{ formatDate(item.date) }}</span><span :class="item.netCents >= 0 ? 'text-[#16836f]' : 'text-[#d65737]'">{{ item.netCents >= 0 ? '+' : '' }}{{ formatMoney(item.netCents) }}</span><strong class="text-[#263e56]">{{ formatMoney(item.balanceCents) }}</strong></div></div></div>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm"><div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><CalendarDays :size="19" /> Histórico diário</div><span class="text-xs text-[#75899c]">Mais recente primeiro</span></div><div v-if="dailySnapshots.length" class="overflow-x-auto"><table class="w-full min-w-[900px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Data</th><th class="px-4 py-3 text-right">Faturamento</th><th class="px-4 py-3 text-right">Unidades</th><th class="px-4 py-3 text-right">CMV</th><th class="px-4 py-3 text-right">Lucro bruto</th><th class="px-4 py-3 text-right">Despesas pagas</th><th class="px-5 py-3 text-right">Variação caixa</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="item in dailySnapshots" :key="item.date"><td class="px-5 py-3.5 text-sm font-semibold text-[#263e56]">{{ formatDate(item.date) }}</td><td class="px-4 py-3.5 text-right text-sm">{{ formatMoney(item.revenueCents) }}</td><td class="px-4 py-3.5 text-right text-sm">{{ item.unitsSold }}</td><td class="px-4 py-3.5 text-right text-sm">{{ formatMoney(item.cogsCents) }}</td><td class="px-4 py-3.5 text-right text-sm font-semibold text-[#087c68]">{{ formatMoney(item.grossProfitCents) }}</td><td class="px-4 py-3.5 text-right text-sm">{{ formatMoney(item.expensesCents) }}</td><td class="px-5 py-3.5 text-right text-sm font-bold" :class="item.cashChangeCents >= 0 ? 'text-[#087c68]' : 'text-[#d65737]'">{{ item.cashChangeCents >= 0 ? '+' : '' }}{{ formatMoney(item.cashChangeCents) }}</td></tr></tbody></table></div><div v-else class="grid min-h-48 place-items-center p-6 text-center"><div><BarChart3 :size="30" class="mx-auto text-[#9babb8]" /><p class="mt-3 text-sm text-[#75899c]">Nenhum dia processado ainda.</p></div></div></section>
        </div>
    </AuthenticatedLayout>
</template>