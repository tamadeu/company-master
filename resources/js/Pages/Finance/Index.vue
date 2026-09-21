<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowDownLeft, ArrowUpRight, CircleDollarSign, Clock3, Landmark, ReceiptText, WalletCards } from '@lucide/vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface GameSummary { id: number; currentDate: string; dayNumber: number; victoryDays: number }
interface CompanySummary { id: number; name: string }
interface FinanceSummary { cashBalanceCents: number; receivablesCents: number; payablesCents: number; overdueCents: number; nextSevenDaysCents: number }
interface IncomeStatement {
    periodStart: string;
    periodEnd: string;
    grossRevenueCents: number;
    salesDeductionsCents: number;
    netRevenueCents: number;
    cogsCents: number;
    grossProfitCents: number;
    operatingExpenses: { payrollCents: number; fixedExpensesCents: number; maintenanceCents: number; otherCents: number };
    operatingExpensesCents: number;
    ebitdaCents: number;
    depreciationAndAmortizationCents: number;
    operatingProfitCents: number;
    financialIncomeCents: number;
    financialExpensesCents: number;
    financialResultCents: number;
    profitBeforeTaxCents: number;
    incomeTaxCents: number;
    netProfitCents: number;
    grossMarginBasisPoints: number;
    ebitdaMarginBasisPoints: number;
    operatingMarginBasisPoints: number;
    netMarginBasisPoints: number;
}
interface CashFlowItem { date: string; inflowsCents: number; outflowsCents: number; netCents: number; balanceCents: number }
interface Entry { id: number; type: 'inflow' | 'outflow'; category: string; description: string; amountCents: number; gameDate: string; dueDate: string | null; settledGameDate: string | null; status: 'settled' | 'overdue' | 'pending' }

const props = defineProps<{
    game: GameSummary;
    company: CompanySummary;
    summary: FinanceSummary;
    incomeStatement: IncomeStatement;
    cashFlow: CashFlowItem[];
    entries: Entry[];
}>();

const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string | null) => date ? new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`)) : '—';
const formatMargin = (basisPoints: number) => `${(basisPoints / 100).toFixed(2)}%`;
const maxFlowCents = computed(() => Math.max(1, ...props.cashFlow.flatMap((item) => [item.inflowsCents, item.outflowsCents])));
const categoryLabels: Record<string, string> = {
    initial_capital: 'Capital inicial',
    fixed_expense: 'Despesa fixa',
    purchase: 'Compra',
    sales_revenue: 'Venda',
    maintenance: 'Manutenção',
    operating_expense: 'Despesa operacional',
    receivable: 'Conta a receber',
    payroll: 'Salário',
    sales_deduction: 'Dedução sobre vendas',
    depreciation: 'Depreciação',
    amortization: 'Amortização',
    financial_income: 'Receita financeira',
    financial_expense: 'Despesa financeira',
    interest_expense: 'Juros',
    income_tax: 'Tributo sobre resultado',
};
</script>

<template>
    <Head title="Financeiro" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6"><h1 class="text-3xl font-bold text-[#102039] lg:text-4xl">Financeiro</h1><p class="mt-1 text-sm text-[#657a90]">Caixa, contas, resultado e trilha de liquidações da empresa.</p></div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo financeiro">
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#dff7f1] text-[#008b76]"><WalletCards :size="22" /></span><div><div class="text-xs text-[#64798d]">Caixa disponível</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(summary.cashBalanceCents) }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#e4f0fb] text-[#1769aa]"><ArrowDownLeft :size="22" /></span><div><div class="text-xs text-[#64798d]">Contas a receber</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(summary.receivablesCents) }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#fff3dd] text-[#b56b00]"><ArrowUpRight :size="22" /></span><div><div class="text-xs text-[#64798d]">Contas a pagar</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(summary.payablesCents) }}</div><div class="text-[11px] text-[#8393a3]">{{ formatMoney(summary.nextSevenDaysCents) }} em 7 dias</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#fee9e7] text-[#d64b3a]"><Clock3 :size="22" /></span><div><div class="text-xs text-[#64798d]">Contas vencidas</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(summary.overdueCents) }}</div></div></div></article>
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-2">
                <div class="rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><Landmark :size="19" /> Fluxo de caixa</div><span class="text-xs text-[#75899c]">Últimos {{ cashFlow.length }} dia(s)</span></div>
                    <div class="space-y-3 p-5">
                        <div v-for="item in cashFlow" :key="item.date" class="grid grid-cols-[5rem_1fr_6rem] items-center gap-3"><span class="text-xs text-[#6e8296]">{{ formatDate(item.date) }}</span><div class="space-y-1"><div class="h-2 overflow-hidden rounded-full bg-[#e8eef3]"><div class="h-full rounded-full bg-[#19b6a5]" :style="{ width: `${Math.max(1, Math.round((item.inflowsCents * 100) / maxFlowCents))}%` }"></div></div><div class="h-2 overflow-hidden rounded-full bg-[#e8eef3]"><div class="h-full rounded-full bg-[#ef806b]" :style="{ width: `${Math.max(1, Math.round((item.outflowsCents * 100) / maxFlowCents))}%` }"></div></div></div><div class="text-right"><div class="text-xs font-bold text-[#263e56]">{{ formatMoney(item.balanceCents) }}</div><div class="text-[10px]" :class="item.netCents >= 0 ? 'text-[#16836f]' : 'text-[#d65737]'">{{ item.netCents >= 0 ? '+' : '' }}{{ formatMoney(item.netCents) }}</div></div></div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><ReceiptText :size="19" /> DRE gerencial</div><span class="text-xs text-[#75899c]">{{ formatDate(incomeStatement.periodStart) }} a {{ formatDate(incomeStatement.periodEnd) }}</span></div>
                    <div class="px-5 py-4 text-sm">
                        <div class="space-y-2.5"><div class="flex justify-between text-[#526a80]"><span>Receita bruta de vendas</span><strong class="text-[#263e56]">{{ formatMoney(incomeStatement.grossRevenueCents) }}</strong></div><div class="flex justify-between text-[#718599]"><span>(−) Deduções sobre vendas</span><strong :class="incomeStatement.salesDeductionsCents ? 'text-[#d65737]' : 'text-[#718599]'">{{ formatMoney(incomeStatement.salesDeductionsCents) }}</strong></div><div class="flex justify-between border-t border-[#dce5ec] pt-2.5 font-bold text-[#19324d]"><span>(=) Receita líquida</span><strong>{{ formatMoney(incomeStatement.netRevenueCents) }}</strong></div><div class="flex justify-between text-[#718599]"><span>(−) Custo das mercadorias vendidas</span><strong class="text-[#d65737]">{{ formatMoney(incomeStatement.cogsCents) }}</strong></div><div class="flex justify-between border-t border-[#dce5ec] pt-2.5 font-bold text-[#19324d]"><span>(=) Lucro bruto</span><strong>{{ formatMoney(incomeStatement.grossProfitCents) }}</strong></div></div>

                        <div class="mt-4 border-t border-[#dce5ec] pt-4"><div class="mb-2 text-[11px] font-bold uppercase text-[#8293a4]">Despesas operacionais</div><div class="space-y-2 text-[#718599]"><div class="flex justify-between pl-3"><span>Pessoal e folha</span><strong class="text-[#d65737]">{{ formatMoney(incomeStatement.operatingExpenses.payrollCents) }}</strong></div><div class="flex justify-between pl-3"><span>Fixas e administrativas</span><strong class="text-[#d65737]">{{ formatMoney(incomeStatement.operatingExpenses.fixedExpensesCents) }}</strong></div><div class="flex justify-between pl-3"><span>Manutenção</span><strong class="text-[#d65737]">{{ formatMoney(incomeStatement.operatingExpenses.maintenanceCents) }}</strong></div><div class="flex justify-between pl-3"><span>Outras despesas operacionais</span><strong class="text-[#d65737]">{{ formatMoney(incomeStatement.operatingExpenses.otherCents) }}</strong></div><div class="flex justify-between border-t border-[#e6edf2] pt-2.5 font-semibold text-[#526a80]"><span>Total das despesas operacionais</span><strong class="text-[#d65737]">{{ formatMoney(incomeStatement.operatingExpensesCents) }}</strong></div></div></div>

                        <div class="mt-3 space-y-2.5"><div class="flex justify-between bg-[#eef6f8] px-3 py-2.5 font-bold text-[#173f67]"><span>EBITDA</span><strong :class="incomeStatement.ebitdaCents >= 0 ? 'text-[#087c68]' : 'text-[#d65737]'">{{ formatMoney(incomeStatement.ebitdaCents) }}</strong></div><div class="flex justify-between text-[#718599]"><span>(−) Depreciação e amortização</span><strong class="text-[#d65737]">{{ formatMoney(incomeStatement.depreciationAndAmortizationCents) }}</strong></div><div class="flex justify-between border-t border-[#dce5ec] pt-2.5 font-bold text-[#19324d]"><span>(=) Resultado operacional</span><strong :class="incomeStatement.operatingProfitCents >= 0 ? 'text-[#087c68]' : 'text-[#d65737]'">{{ formatMoney(incomeStatement.operatingProfitCents) }}</strong></div></div>

                        <div class="mt-4 border-t border-[#dce5ec] pt-4"><div class="mb-2 text-[11px] font-bold uppercase text-[#8293a4]">Resultado financeiro e tributos</div><div class="space-y-2.5 text-[#718599]"><div class="flex justify-between"><span>(+) Receitas financeiras</span><strong class="text-[#087c68]">{{ formatMoney(incomeStatement.financialIncomeCents) }}</strong></div><div class="flex justify-between"><span>(−) Despesas financeiras</span><strong class="text-[#d65737]">{{ formatMoney(incomeStatement.financialExpensesCents) }}</strong></div><div class="flex justify-between font-semibold text-[#526a80]"><span>(=) Resultado financeiro</span><strong :class="incomeStatement.financialResultCents >= 0 ? 'text-[#087c68]' : 'text-[#d65737]'">{{ formatMoney(incomeStatement.financialResultCents) }}</strong></div><div class="flex justify-between border-t border-[#e6edf2] pt-2.5 font-bold text-[#19324d]"><span>Resultado antes dos tributos</span><strong>{{ formatMoney(incomeStatement.profitBeforeTaxCents) }}</strong></div><div class="flex justify-between"><span>(−) Tributos sobre o resultado</span><strong class="text-[#d65737]">{{ formatMoney(incomeStatement.incomeTaxCents) }}</strong></div></div></div>

                        <div class="mt-4 flex justify-between border-t-2 border-[#b9cbd8] pt-4"><span class="text-base font-bold text-[#102039]">Resultado líquido</span><strong class="text-xl" :class="incomeStatement.netProfitCents >= 0 ? 'text-[#087c68]' : 'text-[#d65737]'">{{ formatMoney(incomeStatement.netProfitCents) }}</strong></div>
                    </div>
                    <div class="grid grid-cols-2 border-t border-[#dce5ec] bg-[#f3f7f9] text-center text-xs sm:grid-cols-4"><div class="border-r border-[#dce5ec] p-3"><div class="text-[#718599]">Margem bruta</div><strong class="mt-1 block text-[#263e56]">{{ formatMargin(incomeStatement.grossMarginBasisPoints) }}</strong></div><div class="p-3 sm:border-r sm:border-[#dce5ec]"><div class="text-[#718599]">Margem EBITDA</div><strong class="mt-1 block text-[#263e56]">{{ formatMargin(incomeStatement.ebitdaMarginBasisPoints) }}</strong></div><div class="border-r border-t border-[#dce5ec] p-3 sm:border-t-0"><div class="text-[#718599]">Margem operacional</div><strong class="mt-1 block text-[#263e56]">{{ formatMargin(incomeStatement.operatingMarginBasisPoints) }}</strong></div><div class="border-t border-[#dce5ec] p-3 sm:border-t-0"><div class="text-[#718599]">Margem líquida</div><strong class="mt-1 block" :class="incomeStatement.netMarginBasisPoints >= 0 ? 'text-[#087c68]' : 'text-[#d65737]'">{{ formatMargin(incomeStatement.netMarginBasisPoints) }}</strong></div></div>
                </div>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><CircleDollarSign :size="19" /> Livro financeiro</div><span class="text-xs text-[#75899c]">{{ entries.length }} lançamento(s)</span></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Lançamento</th><th class="px-4 py-3">Competência</th><th class="px-4 py-3">Vencimento</th><th class="px-4 py-3">Liquidação</th><th class="px-4 py-3">Status</th><th class="px-5 py-3 text-right">Valor</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="entry in entries" :key="entry.id"><td class="px-5 py-3.5"><div class="text-sm font-semibold text-[#263e56]">{{ entry.description }}</div><div class="text-[11px] text-[#8a99a8]">{{ categoryLabels[entry.category] ?? entry.category }}</div></td><td class="px-4 py-3.5 text-sm text-[#61758a]">{{ formatDate(entry.gameDate) }}</td><td class="px-4 py-3.5 text-sm text-[#61758a]">{{ formatDate(entry.dueDate) }}</td><td class="px-4 py-3.5 text-sm text-[#61758a]">{{ formatDate(entry.settledGameDate) }}</td><td class="px-4 py-3.5"><span class="rounded px-2 py-1 text-[11px] font-bold" :class="entry.status === 'settled' ? 'bg-[#dff7f1] text-[#087c68]' : entry.status === 'overdue' ? 'bg-[#fee9e7] text-[#c44032]' : 'bg-[#fff3dd] text-[#a56500]'">{{ entry.status === 'settled' ? 'Liquidado' : entry.status === 'overdue' ? 'Vencido' : 'Pendente' }}</span></td><td class="px-5 py-3.5 text-right text-sm font-bold" :class="entry.type === 'inflow' ? 'text-[#087c68]' : 'text-[#d65737]'">{{ entry.type === 'inflow' ? '+' : '−' }}{{ formatMoney(entry.amountCents) }}</td></tr></tbody></table></div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>