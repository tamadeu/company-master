<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CircleDollarSign, ContactRound, Gauge, PackageOpen, ReceiptText, ShoppingBag, TrendingUp, Users } from '@lucide/vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface GameSummary { id: number; currentDate: string; dayNumber: number; victoryDays: number }
interface CompanySummary { id: number; name: string }
interface SalesRules { dailyCapacityUnits: number; commercialEmployees: number; ownerBaseCapacityUnits: number; productivityMinBasisPoints: number; productivityMaxBasisPoints: number; latestUnmetDemandUnits: number }
interface SaleItem { productName: string; quantity: number; unitPriceCents: number; revenueCents: number; cogsCents: number; customerCount: number; sellers: string[] }
interface Sale { id: number; date: string; revenueCents: number; cogsCents: number; grossProfitCents: number; unitsSold: number; customerCount: number; items: SaleItem[] }
interface ProductPerformance { productId: number; productName: string; unitsSold: number; revenueCents: number; grossProfitCents: number }

const props = defineProps<{
    game: GameSummary;
    company: CompanySummary;
    salesRules: SalesRules;
    summary: { revenueCents: number; cogsCents: number; unitsSold: number; processedSales: number; customerPurchases: number };
    sales: Sale[];
    productPerformance: ProductPerformance[];
}>();

const expandedSaleId = ref<number | null>(props.sales[0]?.id ?? null);
const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string) => new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`));
const grossProfitCents = computed(() => props.summary.revenueCents - props.summary.cogsCents);
const maxProductRevenue = computed(() => Math.max(1, ...props.productPerformance.map((item) => item.revenueCents)));
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
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><ContactRound :size="20" class="text-[#6650a4]" /><div class="mt-3 text-xs text-[#64798d]">Compras de clientes</div><div class="text-2xl font-bold text-[#102039]">{{ summary.customerPurchases }}</div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><Gauge :size="20" class="text-[#27658e]" /><div class="mt-3 text-xs text-[#64798d]">Capacidade hoje</div><div class="text-2xl font-bold text-[#102039]">{{ salesRules.dailyCapacityUnits }} un.</div><div class="text-[11px] text-[#8393a3]">{{ salesRules.commercialEmployees }} pessoa(s) no Comercial</div></article>
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-[1.35fr_0.65fr]">
                <div class="overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><ReceiptText :size="19" /> Registros de vendas</div><span class="text-xs text-[#75899c]">{{ sales.length }} dia(s) processado(s)</span></div>
                    <div v-if="sales.length" class="divide-y divide-[#edf1f4]">
                        <article v-for="sale in sales" :key="sale.id">
                            <button type="button" class="grid w-full grid-cols-2 gap-3 px-5 py-4 text-left sm:grid-cols-5" @click="expandedSaleId = expandedSaleId === sale.id ? null : sale.id"><div><span class="block text-[11px] uppercase text-[#8a99a8]">Data</span><strong class="text-sm text-[#263e56]">{{ formatDate(sale.date) }}</strong></div><div><span class="block text-[11px] uppercase text-[#8a99a8]">Faturamento</span><strong class="text-sm text-[#263e56]">{{ formatMoney(sale.revenueCents) }}</strong></div><div><span class="block text-[11px] uppercase text-[#8a99a8]">Unidades</span><strong class="text-sm text-[#263e56]">{{ sale.unitsSold }}</strong></div><div><span class="block text-[11px] uppercase text-[#8a99a8]">Clientes</span><strong class="text-sm text-[#263e56]">{{ sale.customerCount }}</strong></div><div><span class="block text-[11px] uppercase text-[#8a99a8]">Lucro bruto</span><strong class="text-sm text-[#087c68]">{{ formatMoney(sale.grossProfitCents) }}</strong></div></button>
                            <div v-if="expandedSaleId === sale.id" class="border-t border-[#edf1f4] bg-[#fbfcfd] px-5 py-4"><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left"><thead class="text-[10px] uppercase text-[#8a99a8]"><tr><th class="py-2">Produto</th><th class="py-2 text-right">Qtd.</th><th class="py-2 text-right">Preço</th><th class="py-2 text-right">Receita</th><th class="py-2 text-right">Clientes</th><th class="py-2">Responsáveis</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="item in sale.items" :key="item.productName"><td class="py-2.5 text-sm font-semibold text-[#354d64]">{{ item.productName }}</td><td class="py-2.5 text-right text-sm">{{ item.quantity }}</td><td class="py-2.5 text-right text-sm">{{ formatMoney(item.unitPriceCents) }}</td><td class="py-2.5 text-right text-sm font-bold text-[#087c68]">{{ formatMoney(item.revenueCents) }}</td><td class="py-2.5 text-right text-sm">{{ item.customerCount }}</td><td class="py-2.5 text-sm text-[#61758a]">{{ item.sellers.join(', ') }}</td></tr></tbody></table></div></div>
                        </article>
                    </div>
                    <div v-else class="grid min-h-64 place-items-center p-8 text-center"><div><ReceiptText :size="34" class="mx-auto text-[#9babb8]" /><h2 class="mt-3 text-sm font-bold text-[#354d64]">Nenhuma venda processada</h2><p class="mt-1 text-xs text-[#7a8c9d]">Vendas aparecerão aqui depois que um dia for avançado com estoque disponível.</p></div></div>
                </div>

                <div class="h-fit rounded-md border border-[#dfe7ee] bg-white shadow-sm"><div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><PackageOpen :size="19" /> Desempenho por produto</div></div><div v-if="productPerformance.length" class="space-y-4 p-5"><div v-for="item in productPerformance" :key="item.productId"><div class="mb-1 flex justify-between gap-3 text-xs"><span class="font-semibold text-[#354d64]">{{ item.productName }} · {{ item.unitsSold }} un.</span><strong>{{ formatMoney(item.revenueCents) }}</strong></div><div class="h-2 overflow-hidden rounded-full bg-[#e8eef3]"><div class="h-full rounded-full bg-[#19b6a5]" :style="{ width: `${Math.max(2, Math.round((item.revenueCents * 100) / maxProductRevenue))}%` }"></div></div></div></div><div v-else class="p-6 text-center text-sm text-[#7a8c9d]">Sem desempenho registrado.</div></div>
            </section>

            <section class="mt-4 rounded-md border border-[#dfe7ee] bg-white p-5 shadow-sm"><div class="flex items-start gap-3"><Users :size="21" class="mt-0.5 shrink-0 text-[#27658e]" /><div><h2 class="text-sm font-bold text-[#19324d]">Regra operacional</h2><p class="mt-1 text-sm leading-6 text-[#6b7f93]">As vendas são automáticas ao avançar o dia e respeitam demanda, estoque e capacidade comercial. Configuração de preço fica em <strong>Estoque e produtos</strong>. O gestor cobre {{ salesRules.ownerBaseCapacityUnits }} un./dia e a equipe Comercial amplia o limite.</p><p v-if="salesRules.latestUnmetDemandUnits" class="mt-1 text-xs font-semibold text-[#c45027]">Último dia: {{ salesRules.latestUnmetDemandUnits }} unidade(s) de demanda não atendida.</p></div></div></section>
        </div>
    </AuthenticatedLayout>
</template>