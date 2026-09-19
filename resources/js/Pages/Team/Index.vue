<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { BadgeDollarSign, Building2, CalendarClock, Gauge, UserMinus, UserPlus, Users, X } from '@lucide/vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

interface GameSummary { id: number; currentDate: string; dayNumber: number; victoryDays: number; status: string }
interface CompanySummary { id: number; name: string }
interface TeamSummary { activeEmployees: number; terminatedEmployees: number; monthlyPayrollCents: number; nextPayrollDate: string | null; nextPayrollCents: number; commercialEmployees: number; salesCapacityUnits: number }
interface Employee { id: number; name: string; department: string; role: string; monthlySalaryCents: number; hiredOn: string; terminatedOn: string | null; status: 'active' | 'terminated'; nextSalaryDueDate: string | null; attributedUnitsSold: number; attributedRevenueCents: number }

const props = defineProps<{
    game: GameSummary;
    company: CompanySummary;
    summary: TeamSummary;
    employees: Employee[];
    options: { departments: string[]; roles: string[]; payrollDay: number };
}>();

const hireOpen = ref(false);
const employeeToTerminate = ref<Employee | null>(null);
const salaryInput = ref('');
const localErrors = reactive<{ salary?: string }>({});
const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string | null) => date ? new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`)) : '—';
const form = useForm({
    name: '',
    department: props.options.departments[0] ?? '',
    role: props.options.roles[0] ?? '',
    monthly_salary_cents: 0,
});

const parseCents = (value: string): number | null => {
    const match = value.trim().match(/^(\d+)(?:[,.](\d{1,2}))?$/);
    if (!match) return null;

    return (Number(match[1]) * 100) + Number((match[2] ?? '').padEnd(2, '0'));
};

const submitHire = () => {
    const cents = parseCents(salaryInput.value);
    if (!cents) {
        localErrors.salary = 'Informe um salário válido, como 3.500,00 sem separador de milhar.';
        return;
    }

    localErrors.salary = '';
    form.monthly_salary_cents = cents;
    form.post(route('games.employees.store', props.game.id), {
        preserveScroll: true,
        onSuccess: () => {
            hireOpen.value = false;
            salaryInput.value = '';
            form.reset();
        },
    });
};

const terminateEmployee = () => {
    if (!employeeToTerminate.value) return;

    router.delete(route('games.employees.terminate', [props.game.id, employeeToTerminate.value.id]), {
        preserveScroll: true,
        onFinish: () => {
            employeeToTerminate.value = null;
        },
    });
};
</script>

<template>
    <Head title="Equipe e RH" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><h1 class="text-3xl font-bold text-[#102039] lg:text-4xl">Equipe e RH</h1><p class="mt-1 text-sm text-[#657a90]">Gerencie pessoas e acompanhe o impacto mensal da folha.</p></div><button type="button" :disabled="game.status !== 'active'" class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-[#ef654f] px-5 text-sm font-bold text-white disabled:opacity-50" @click="hireOpen = true"><UserPlus :size="18" /> Contratar funcionário</button></div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Resumo de RH">
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#dff7f1] text-[#008b76]"><Users :size="22" /></span><div><div class="text-xs text-[#64798d]">Funcionários ativos</div><div class="text-2xl font-bold text-[#102039]">{{ summary.activeEmployees }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#e4f0fb] text-[#1769aa]"><BadgeDollarSign :size="22" /></span><div><div class="text-xs text-[#64798d]">Folha mensal ativa</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(summary.monthlyPayrollCents) }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#fff3dd] text-[#b56b00]"><CalendarClock :size="22" /></span><div><div class="text-xs text-[#64798d]">Próxima folha</div><div class="text-lg font-bold text-[#102039]">{{ formatDate(summary.nextPayrollDate) }}</div><div class="text-[11px] text-[#8393a3]">{{ formatMoney(summary.nextPayrollCents) }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#f0edf8] text-[#6650a4]"><Building2 :size="22" /></span><div><div class="text-xs text-[#64798d]">Departamentos</div><div class="text-2xl font-bold text-[#102039]">{{ new Set(employees.filter((item) => item.status === 'active').map((item) => item.department)).size }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#e7f4f8] text-[#27658e]"><Gauge :size="22" /></span><div><div class="text-xs text-[#64798d]">Capacidade comercial</div><div class="text-2xl font-bold text-[#102039]">{{ summary.salesCapacityUnits }} un.</div><div class="text-[11px] text-[#8393a3]">{{ summary.commercialEmployees }} vendedor(es)</div></div></div></article>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm"><div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><Users :size="19" /> Quadro de funcionários</div><span class="text-xs text-[#75899c]">Pagamento no dia {{ options.payrollDay }}</span></div><div v-if="employees.length" class="overflow-x-auto"><table class="w-full min-w-[1080px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Funcionário</th><th class="px-4 py-3">Departamento</th><th class="px-4 py-3">Cargo</th><th class="px-4 py-3 text-right">Salário</th><th class="px-4 py-3 text-right">Vendas</th><th class="px-4 py-3 text-right">Receita atribuída</th><th class="px-4 py-3">Status</th><th class="px-5 py-3 text-right">Ação</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="employee in employees" :key="employee.id"><td class="px-5 py-3.5"><div class="text-sm font-semibold text-[#263e56]">{{ employee.name }}</div><div class="text-[11px] text-[#8a99a8]">Admissão {{ formatDate(employee.hiredOn) }}</div></td><td class="px-4 py-3.5 text-sm text-[#61758a]">{{ employee.department }}</td><td class="px-4 py-3.5 text-sm text-[#61758a]">{{ employee.role }}</td><td class="px-4 py-3.5 text-right text-sm font-bold text-[#263e56]">{{ formatMoney(employee.monthlySalaryCents) }}</td><td class="px-4 py-3.5 text-right text-sm font-semibold text-[#354d64]">{{ employee.attributedUnitsSold }} un.</td><td class="px-4 py-3.5 text-right text-sm font-bold text-[#087c68]">{{ formatMoney(employee.attributedRevenueCents) }}</td><td class="px-4 py-3.5"><span class="rounded px-2 py-1 text-[11px] font-bold" :class="employee.status === 'active' ? 'bg-[#dff7f1] text-[#087c68]' : 'bg-[#edf1f4] text-[#687b8d]'">{{ employee.status === 'active' ? 'Ativo' : 'Desligado' }}</span></td><td class="px-5 py-3.5 text-right"><button v-if="employee.status === 'active' && game.status === 'active'" type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[#e5b7b0] px-3 text-xs font-bold text-[#c44032] hover:bg-[#fff0ed]" @click="employeeToTerminate = employee"><UserMinus :size="14" /> Desligar</button><span v-else class="text-xs text-[#8a99a8]">{{ formatDate(employee.terminatedOn) }}</span></td></tr></tbody></table></div><div v-else class="grid min-h-56 place-items-center p-8 text-center"><div><Users :size="34" class="mx-auto text-[#9babb8]" /><h2 class="mt-3 text-sm font-bold text-[#354d64]">Nenhum funcionário contratado</h2><p class="mt-1 text-xs text-[#7a8c9d]">Contrate sua primeira pessoa e acompanhe a folha no financeiro.</p></div></div></section>
        </div>

        <div v-if="hireOpen" class="fixed inset-0 z-[80] grid place-items-center overflow-y-auto bg-[#071729]/60 p-4" @click.self="hireOpen = false"><div class="w-full max-w-lg rounded-md bg-white shadow-2xl"><div class="flex items-start justify-between border-b border-[#e2e9ee] px-6 py-5"><div><h2 class="text-xl font-bold text-[#102039]">Contratar funcionário</h2><p class="mt-1 text-sm text-[#6b7f93]">O salário será incluído nas obrigações mensais.</p></div><button type="button" class="grid size-9 place-items-center text-[#687d91]" aria-label="Fechar" @click="hireOpen = false"><X :size="20" /></button></div><form class="space-y-4 p-6" @submit.prevent="submitHire"><div><label for="employee-name" class="text-sm font-semibold text-[#29415a]">Nome</label><input id="employee-name" v-model="form.name" required maxlength="100" class="mt-2 block w-full rounded-md border-[#cdd9e2] text-sm focus:border-[#19a895] focus:ring-[#19a895]" /><p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p></div><div class="grid gap-4 sm:grid-cols-2"><div><label for="department" class="text-sm font-semibold text-[#29415a]">Departamento</label><select id="department" v-model="form.department" class="mt-2 block w-full rounded-md border-[#cdd9e2] text-sm"><option v-for="item in options.departments" :key="item">{{ item }}</option></select></div><div><label for="role" class="text-sm font-semibold text-[#29415a]">Cargo</label><select id="role" v-model="form.role" class="mt-2 block w-full rounded-md border-[#cdd9e2] text-sm"><option v-for="item in options.roles" :key="item">{{ item }}</option></select></div></div><div><label for="salary" class="text-sm font-semibold text-[#29415a]">Salário mensal</label><div class="mt-2 flex items-center rounded-md border border-[#cdd9e2] bg-white px-3 focus-within:border-[#19a895] focus-within:ring-1 focus-within:ring-[#19a895]"><span class="text-sm text-[#6b7f93]">R$</span><input id="salary" v-model="salaryInput" inputmode="decimal" placeholder="3500,00" class="w-full border-0 text-sm focus:ring-0" /></div><p v-if="localErrors.salary || form.errors.monthly_salary_cents" class="mt-1 text-xs text-red-600">{{ localErrors.salary || form.errors.monthly_salary_cents }}</p></div><button type="submit" :disabled="form.processing" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-[#ef654f] text-sm font-bold text-white disabled:opacity-50"><UserPlus :size="17" /> {{ form.processing ? 'Contratando...' : 'Confirmar contratação' }}</button></form></div></div>

        <div v-if="employeeToTerminate" class="fixed inset-0 z-[90] grid place-items-center bg-[#071729]/60 p-4" @click.self="employeeToTerminate = null"><div class="w-full max-w-md rounded-md bg-white p-6 shadow-2xl"><span class="grid size-11 place-items-center rounded-md bg-[#fee9e7] text-[#c44032]"><UserMinus :size="21" /></span><h2 class="mt-4 text-xl font-bold text-[#102039]">Desligar {{ employeeToTerminate.name }}?</h2><p class="mt-2 text-sm leading-6 text-[#6b7f93]">A obrigação salarial já assumida será mantida, mas novas competências não serão geradas.</p><div class="mt-6 flex justify-end gap-2"><button type="button" class="h-10 rounded-md border border-[#cfdbe4] px-4 text-sm font-bold text-[#496177]" @click="employeeToTerminate = null">Cancelar</button><button type="button" class="h-10 rounded-md bg-[#c44032] px-4 text-sm font-bold text-white" @click="terminateEmployee">Confirmar desligamento</button></div></div></div>
    </AuthenticatedLayout>
</template>