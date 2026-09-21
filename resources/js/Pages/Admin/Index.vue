<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { BriefcaseBusiness, ContactRound, Gamepad2, LayoutDashboard, MailPlus, Package, Pencil, Plus, Save, Search, Send, ShieldCheck, Trash2, Users, X } from '@lucide/vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface PageLink { url: string | null; label: string; active: boolean }
interface Paginated<T> { data: T[]; links: PageLink[]; from: number | null; to: number | null; total: number }
interface UserRecord { id: number; name: string; email: string; is_admin: boolean; games_count: number; created_at: string }
interface PlayerOption { id: number; name: string; email: string }
interface GameRecord { id: number; user_id: number; name: string; status: 'active' | 'won' | 'bankrupt'; current_date: string; created_at: string; user: PlayerOption; company: { name: string; cash_balance_cents: number; customers_count: number; employees_count: number; products_count: number } }
interface PopulationRecord { id: number; code: string; name: string; birth_date: string; gender: string; city: string; state: string; email: string | null; customers_count: number; employees_count: number }
interface ProductRecord { id: number; sku: string; name: string; reference_price_cents: number; base_daily_demand: number; base_cost_cents: number; active: boolean }
interface JobRoleRecord { id: number; department: string; name: string; salary_cents: number; sales_capacity_units: number; inventory_capacity_units: number; active: boolean }
interface RecipientOption { id: number; name: string; email: string; is_admin: boolean }
interface SentMessageRecord { id: number; subject: string; body: string; action_url: string | null; read_at: string | null; created_at: string; recipient: RecipientOption }

const props = defineProps<{
    section: 'overview' | 'users' | 'games' | 'population' | 'products' | 'job-roles' | 'messages';
    stats?: { users: number; games: number; activeGames: number; population: number; products: number; jobRoles: number };
    recentUsers?: UserRecord[];
    filters?: { search?: string };
    records?: Paginated<UserRecord | GameRecord | PopulationRecord | ProductRecord | JobRoleRecord | SentMessageRecord>;
    players?: PlayerOption[];
    recipients?: RecipientOption[];
}>();

const sections = [
    { key: 'overview', label: 'Visão geral', icon: LayoutDashboard, href: route('admin.overview') },
    { key: 'users', label: 'Usuários', icon: Users, href: route('admin.users.index') },
    { key: 'games', label: 'Partidas', icon: Gamepad2, href: route('admin.games.index') },
    { key: 'population', label: 'População', icon: ContactRound, href: route('admin.population.index') },
    { key: 'products', label: 'Produtos', icon: Package, href: route('admin.products.index') },
    { key: 'job-roles', label: 'Cargos', icon: BriefcaseBusiness, href: route('admin.job-roles.index') },
    { key: 'messages', label: 'Mensagens', icon: MailPlus, href: route('admin.messages.index') },
];
const title = computed(() => sections.find((item) => item.key === props.section)?.label ?? 'Administração');
const search = ref(props.filters?.search ?? '');
const users = computed(() => props.records as Paginated<UserRecord> | undefined);
const games = computed(() => props.records as Paginated<GameRecord> | undefined);
const population = computed(() => props.records as Paginated<PopulationRecord> | undefined);
const products = computed(() => props.records as Paginated<ProductRecord> | undefined);
const jobRoles = computed(() => props.records as Paginated<JobRoleRecord> | undefined);
const sentMessages = computed(() => props.records as Paginated<SentMessageRecord> | undefined);
const editingId = ref<number | null>(null);

const userForm = useForm({ name: '', email: '', password: '', password_confirmation: '', is_admin: false });
const gameForm = useForm({ user_id: 0, name: '', company_name: '', status: 'active', current_date: '' });
const populationForm = useForm({ code: '', name: '', birth_date: '', gender: 'Feminino', city: '', state: '', email: '' });
const productForm = useForm({ sku: '', name: '', reference_price_cents: 0, base_daily_demand: 1, base_cost_cents: 0, active: true });
const roleForm = useForm({ department: '', name: '', salary_cents: 0, sales_capacity_units: 0, inventory_capacity_units: 0, active: true });
const messageForm = useForm({ audience: 'user', recipient_user_id: '', subject: '', body: '', action_url: '' });

const resetEditor = () => {
    editingId.value = null;
    userForm.reset();
    gameForm.reset();
    populationForm.reset();
    productForm.reset();
    roleForm.reset();
    messageForm.reset();
    userForm.clearErrors();
    gameForm.clearErrors();
    populationForm.clearErrors();
    productForm.clearErrors();
    roleForm.clearErrors();
    messageForm.clearErrors();
};

const runSearch = () => {
    const routeName = `admin.${props.section === 'job-roles' ? 'job-roles' : props.section}.index`;
    router.get(route(routeName), { search: search.value }, { preserveState: true, replace: true });
};

const editUser = (user: UserRecord) => {
    editingId.value = user.id;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.is_admin = user.is_admin;
};
const saveUser = () => {
    const options = { preserveScroll: true, onSuccess: resetEditor };
    if (editingId.value) {
        userForm.patch(route('admin.users.update', editingId.value), options);
    } else {
        userForm.post(route('admin.users.store'), options);
    }
};

const editGame = (game: GameRecord) => {
    editingId.value = game.id;
    gameForm.user_id = game.user_id;
    gameForm.name = game.name;
    gameForm.company_name = game.company.name;
    gameForm.status = game.status;
    gameForm.current_date = game.current_date;
};
const saveGame = () => {
    if (!editingId.value) return;
    gameForm.patch(route('admin.games.update', editingId.value), { preserveScroll: true, onSuccess: resetEditor });
};

const editPopulation = (person: PopulationRecord) => {
    editingId.value = person.id;
    populationForm.code = person.code;
    populationForm.name = person.name;
    populationForm.birth_date = person.birth_date;
    populationForm.gender = person.gender;
    populationForm.city = person.city;
    populationForm.state = person.state;
    populationForm.email = person.email ?? '';
};
const savePopulation = () => {
    const options = { preserveScroll: true, onSuccess: resetEditor };
    if (editingId.value) {
        populationForm.patch(route('admin.population.update', editingId.value), options);
    } else {
        populationForm.post(route('admin.population.store'), options);
    }
};

const editProduct = (product: ProductRecord) => {
    editingId.value = product.id;
    productForm.sku = product.sku;
    productForm.name = product.name;
    productForm.reference_price_cents = product.reference_price_cents;
    productForm.base_daily_demand = product.base_daily_demand;
    productForm.base_cost_cents = product.base_cost_cents;
    productForm.active = product.active;
};
const saveProduct = () => {
    const options = { preserveScroll: true, onSuccess: resetEditor };
    if (editingId.value) {
        productForm.patch(route('admin.products.update', editingId.value), options);
    } else {
        productForm.post(route('admin.products.store'), options);
    }
};

const editJobRole = (jobRole: JobRoleRecord) => {
    editingId.value = jobRole.id;
    roleForm.department = jobRole.department;
    roleForm.name = jobRole.name;
    roleForm.salary_cents = jobRole.salary_cents;
    roleForm.sales_capacity_units = jobRole.sales_capacity_units;
    roleForm.inventory_capacity_units = jobRole.inventory_capacity_units;
    roleForm.active = jobRole.active;
};
const saveJobRole = () => {
    const options = { preserveScroll: true, onSuccess: resetEditor };
    if (editingId.value) {
        roleForm.patch(route('admin.job-roles.update', editingId.value), options);
    } else {
        roleForm.post(route('admin.job-roles.store'), options);
    }
};

const sendMessage = () => {
    messageForm.post(route('admin.messages.store'), {
        preserveScroll: true,
        onSuccess: () => messageForm.reset(),
    });
};

const formatDateTime = (value: string) => new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
}).format(new Date(value));

const destroyRecord = (routeName: string, id: number, message: string) => {
    if (window.confirm(message)) router.delete(route(routeName, id), { preserveScroll: true });
};
const formatMoney = (cents: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
const cleanLabel = (label: string) => ({
    'pagination.previous': 'Anterior',
    'pagination.next': 'Próxima',
}[label] ?? label.replace('&laquo;', '').replace('&raquo;', '').trim());
</script>

<template>
    <Head :title="`${title} · Administração`" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <header class="flex flex-col gap-4 border-b border-[#d7e2ea] pb-6 lg:flex-row lg:items-end lg:justify-between">
                <div><div class="flex items-center gap-2 text-xs font-bold uppercase text-[#087c68]"><ShieldCheck :size="16" /> Administração</div><h1 class="mt-2 text-3xl font-bold text-[#102039]">{{ title }}</h1><p class="mt-1 text-sm text-[#657a90]">Gerencie os dados mestres e o acesso à simulação.</p></div>
                <nav class="flex gap-1 overflow-x-auto" aria-label="Módulos administrativos"><Link v-for="item in sections" :key="item.key" :href="item.href" class="flex h-10 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-semibold" :class="section === item.key ? 'bg-[#173f67] text-white' : 'text-[#526a80] hover:bg-white'"><component :is="item.icon" :size="17" />{{ item.label }}</Link></nav>
            </header>

            <template v-if="section === 'overview' && stats">
                <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <article v-for="item in [{ label: 'Usuários', value: stats.users, icon: Users }, { label: 'Partidas', value: stats.games, icon: Gamepad2 }, { label: 'Partidas ativas', value: stats.activeGames, icon: LayoutDashboard }, { label: 'População global', value: stats.population, icon: ContactRound }, { label: 'Produtos ativos', value: stats.products, icon: Package }, { label: 'Cargos ativos', value: stats.jobRoles, icon: BriefcaseBusiness }]" :key="item.label" class="border-l-4 border-[#24b9a5] bg-white p-5 shadow-sm"><component :is="item.icon" :size="20" class="text-[#17638d]" /><div class="mt-5 text-3xl font-bold text-[#102039]">{{ item.value }}</div><div class="mt-1 text-xs font-semibold text-[#718499]">{{ item.label }}</div></article>
                </section>
                <section class="mt-5 overflow-hidden border border-[#dfe7ee] bg-white"><div class="border-b border-[#e6edf2] px-5 py-4 text-sm font-bold text-[#19324d]">Usuários recentes</div><table class="w-full text-left"><thead class="bg-[#f8fafc] text-xs uppercase text-[#74879a]"><tr><th class="px-5 py-3">Nome</th><th class="px-4 py-3">E-mail</th><th class="px-5 py-3">Acesso</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="user in recentUsers" :key="user.id"><td class="px-5 py-4 text-sm font-semibold">{{ user.name }}</td><td class="px-4 py-4 text-sm text-[#61758a]">{{ user.email }}</td><td class="px-5 py-4 text-xs font-bold" :class="user.is_admin ? 'text-[#087c68]' : 'text-[#718499]'">{{ user.is_admin ? 'Administrador' : 'Jogador' }}</td></tr></tbody></table></section>
            </template>

            <template v-else>
                <div class="mt-6 grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
                    <section class="self-start border border-[#dfe7ee] bg-white p-5 shadow-sm">
                        <div class="mb-5 flex items-center justify-between"><h2 class="text-sm font-bold text-[#19324d]">{{ editingId ? 'Editar registro' : section === 'games' ? 'Selecione uma partida' : section === 'messages' ? 'Nova mensagem' : 'Novo registro' }}</h2><button v-if="editingId" type="button" class="grid size-8 place-items-center text-[#718499]" title="Cancelar edição" @click="resetEditor"><X :size="18" /></button></div>

                        <form v-if="section === 'users'" class="space-y-4" @submit.prevent="saveUser"><label class="block text-xs font-semibold">Nome<input v-model="userForm.name" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">E-mail<input v-model="userForm.email" type="email" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Senha {{ editingId ? '(opcional)' : '' }}<input v-model="userForm.password" type="password" :required="!editingId" class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Confirmar senha<input v-model="userForm.password_confirmation" type="password" :required="!editingId" class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="flex items-center gap-2 text-sm"><input v-model="userForm.is_admin" type="checkbox" class="rounded border-[#ccd8e1] text-[#173f67]" /> Acesso administrativo</label><div v-if="Object.keys(userForm.errors).length" class="text-xs text-red-600">{{ Object.values(userForm.errors)[0] }}</div><button :disabled="userForm.processing" class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-[#173f67] text-sm font-bold text-white"><Save :size="16" /> Salvar usuário</button></form>

                        <form v-if="section === 'messages'" class="space-y-4" @submit.prevent="sendMessage"><label class="block text-xs font-semibold">Destinatários<select v-model="messageForm.audience" class="mt-1 w-full rounded-md border-[#ccd8e1]"><option value="user">Usuário específico</option><option value="all_players">Todos os jogadores</option></select></label><label v-if="messageForm.audience === 'user'" class="block text-xs font-semibold">Usuário<select v-model="messageForm.recipient_user_id" required class="mt-1 w-full rounded-md border-[#ccd8e1]"><option value="" disabled>Selecione</option><option v-for="recipient in recipients" :key="recipient.id" :value="recipient.id">{{ recipient.name }} · {{ recipient.email }}</option></select></label><label class="block text-xs font-semibold">Assunto<input v-model="messageForm.subject" maxlength="160" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Mensagem<textarea v-model="messageForm.body" rows="7" maxlength="5000" required class="mt-1 w-full resize-y rounded-md border-[#ccd8e1]"></textarea></label><label class="block text-xs font-semibold">Link interno (opcional)<input v-model="messageForm.action_url" placeholder="/dashboard" class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><div v-if="Object.keys(messageForm.errors).length" class="text-xs text-red-600">{{ Object.values(messageForm.errors)[0] }}</div><button :disabled="messageForm.processing" class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-[#173f67] text-sm font-bold text-white"><Send :size="16" /> Enviar mensagem</button></form>

                        <form v-if="section === 'games' && editingId" class="space-y-3" @submit.prevent="saveGame"><label class="block text-xs font-semibold">Jogador<select v-model="gameForm.user_id" required class="mt-1 w-full rounded-md border-[#ccd8e1]"><option v-for="player in players" :key="player.id" :value="player.id">{{ player.name }} · {{ player.email }}</option></select></label><label class="block text-xs font-semibold">Nome da partida<input v-model="gameForm.name" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Empresa<input v-model="gameForm.company_name" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><div class="grid grid-cols-2 gap-3"><label class="block text-xs font-semibold">Status<select v-model="gameForm.status" class="mt-1 w-full rounded-md border-[#ccd8e1]"><option value="active">Ativa</option><option value="won">Vitória</option><option value="bankrupt">Falência</option></select></label><label class="block text-xs font-semibold">Data do jogo<input v-model="gameForm.current_date" type="date" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label></div><div v-if="Object.keys(gameForm.errors).length" class="text-xs text-red-600">{{ Object.values(gameForm.errors)[0] }}</div><button :disabled="gameForm.processing" class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-[#173f67] text-sm font-bold text-white"><Save :size="16" /> Salvar partida</button></form><p v-else-if="section === 'games'" class="text-sm leading-6 text-[#718499]">Use o botão de edição na tabela para alterar proprietário, empresa, status ou calendário.</p>

                        <form v-if="section === 'population'" class="space-y-3" @submit.prevent="savePopulation"><div class="grid grid-cols-2 gap-3"><label class="block text-xs font-semibold">Código<input v-model="populationForm.code" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Gênero<input v-model="populationForm.gender" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label></div><label class="block text-xs font-semibold">Nome<input v-model="populationForm.name" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Nascimento<input v-model="populationForm.birth_date" type="date" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><div class="grid grid-cols-[1fr_76px] gap-3"><label class="block text-xs font-semibold">Cidade<input v-model="populationForm.city" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">UF<input v-model="populationForm.state" maxlength="2" required class="mt-1 w-full rounded-md border-[#ccd8e1] uppercase" /></label></div><label class="block text-xs font-semibold">E-mail<input v-model="populationForm.email" type="email" class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><div v-if="Object.keys(populationForm.errors).length" class="text-xs text-red-600">{{ Object.values(populationForm.errors)[0] }}</div><button :disabled="populationForm.processing" class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-[#173f67] text-sm font-bold text-white"><Save :size="16" /> Salvar pessoa</button></form>

                        <form v-if="section === 'products'" class="space-y-3" @submit.prevent="saveProduct"><div class="grid grid-cols-2 gap-3"><label class="block text-xs font-semibold">SKU<input v-model="productForm.sku" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Demanda/dia<input v-model.number="productForm.base_daily_demand" type="number" min="1" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label></div><label class="block text-xs font-semibold">Nome<input v-model="productForm.name" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Preço de referência (centavos)<input v-model.number="productForm.reference_price_cents" type="number" min="1" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Custo base (centavos)<input v-model.number="productForm.base_cost_cents" type="number" min="1" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="flex items-center gap-2 text-sm"><input v-model="productForm.active" type="checkbox" class="rounded border-[#ccd8e1] text-[#173f67]" /> Disponível em novas partidas</label><div v-if="Object.keys(productForm.errors).length" class="text-xs text-red-600">{{ Object.values(productForm.errors)[0] }}</div><button :disabled="productForm.processing" class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-[#173f67] text-sm font-bold text-white"><Save :size="16" /> Salvar produto</button></form>

                        <form v-if="section === 'job-roles'" class="space-y-3" @submit.prevent="saveJobRole"><label class="block text-xs font-semibold">Departamento<input v-model="roleForm.department" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Cargo<input v-model="roleForm.name" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Salário mensal (centavos)<input v-model.number="roleForm.salary_cents" type="number" min="1" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><div class="grid grid-cols-2 gap-3"><label class="block text-xs font-semibold">Cap. vendas<input v-model.number="roleForm.sales_capacity_units" type="number" min="0" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label><label class="block text-xs font-semibold">Cap. estoque<input v-model.number="roleForm.inventory_capacity_units" type="number" min="0" required class="mt-1 w-full rounded-md border-[#ccd8e1]" /></label></div><label class="flex items-center gap-2 text-sm"><input v-model="roleForm.active" type="checkbox" class="rounded border-[#ccd8e1] text-[#173f67]" /> Disponível para contratação</label><div v-if="Object.keys(roleForm.errors).length" class="text-xs text-red-600">{{ Object.values(roleForm.errors)[0] }}</div><button :disabled="roleForm.processing" class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-[#173f67] text-sm font-bold text-white"><Save :size="16" /> Salvar cargo</button></form>
                    </section>

                    <section class="min-w-0 overflow-hidden border border-[#dfe7ee] bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-[#e6edf2] p-4 sm:flex-row sm:items-center sm:justify-between"><form v-if="section !== 'messages'" class="flex max-w-2xl flex-1 flex-col gap-2 sm:flex-row" @submit.prevent="runSearch"><div class="relative flex-1"><Search :size="17" class="absolute left-3 top-2.5 text-[#8293a4]" /><input v-model="search" placeholder="Buscar..." class="h-10 w-full rounded-md border-[#ccd8e1] pl-9 text-sm" /></div><button class="h-10 rounded-md bg-[#173f67] px-4 text-sm font-bold text-white">Buscar</button></form><div v-else class="text-sm font-bold text-[#19324d]">Histórico de envios</div><button v-if="section !== 'games'" type="button" class="flex h-10 items-center justify-center gap-2 rounded-md border border-[#ccd8e1] px-3 text-sm font-semibold text-[#526a80] xl:hidden" @click="resetEditor"><Plus :size="16" /> Novo</button></div>
                        <div class="overflow-x-auto">
                            <table v-if="section === 'users'" class="w-full min-w-[720px] text-left"><thead class="bg-[#f8fafc] text-xs uppercase text-[#74879a]"><tr><th class="px-5 py-3">Usuário</th><th class="px-4 py-3">Perfil</th><th class="px-4 py-3 text-right">Partidas</th><th class="px-5 py-3 text-right">Ações</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="user in users?.data" :key="user.id"><td class="px-5 py-4"><div class="text-sm font-semibold">{{ user.name }}</div><div class="text-xs text-[#718499]">{{ user.email }}</div></td><td class="px-4 py-4 text-xs font-bold" :class="user.is_admin ? 'text-[#087c68]' : 'text-[#718499]'">{{ user.is_admin ? 'Administrador' : 'Jogador' }}</td><td class="px-4 py-4 text-right text-sm">{{ user.games_count }}</td><td class="px-5 py-4"><div class="flex justify-end gap-1"><button title="Editar" class="grid size-9 place-items-center text-[#17638d]" @click="editUser(user)"><Pencil :size="16" /></button><button title="Excluir" class="grid size-9 place-items-center text-red-600" @click="destroyRecord('admin.users.destroy', user.id, `Excluir ${user.name} e todas as suas partidas?`)"><Trash2 :size="16" /></button></div></td></tr></tbody></table>
                            <table v-if="section === 'messages'" class="w-full min-w-[760px] text-left"><thead class="bg-[#f8fafc] text-xs uppercase text-[#74879a]"><tr><th class="px-5 py-3">Assunto</th><th class="px-4 py-3">Destinatário</th><th class="px-4 py-3">Status</th><th class="px-5 py-3 text-right">Envio</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="message in sentMessages?.data" :key="message.id"><td class="px-5 py-4"><div class="text-sm font-semibold">{{ message.subject }}</div><div class="mt-1 max-w-md truncate text-xs text-[#718499]">{{ message.body }}</div></td><td class="px-4 py-4"><div class="text-sm">{{ message.recipient.name }}</div><div class="text-xs text-[#718499]">{{ message.recipient.email }}</div></td><td class="px-4 py-4 text-xs font-bold" :class="message.read_at ? 'text-[#087c68]' : 'text-[#718499]'">{{ message.read_at ? 'Lida' : 'Não lida' }}</td><td class="px-5 py-4 text-right text-xs text-[#718499]">{{ formatDateTime(message.created_at) }}</td></tr></tbody></table>
                            <table v-if="section === 'games'" class="w-full min-w-[980px] text-left"><thead class="bg-[#f8fafc] text-xs uppercase text-[#74879a]"><tr><th class="px-5 py-3">Partida</th><th class="px-4 py-3">Jogador</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Operação</th><th class="px-4 py-3 text-right">Caixa</th><th class="px-5 py-3 text-right">Ações</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="game in games?.data" :key="game.id"><td class="px-5 py-4"><div class="text-sm font-semibold">{{ game.name }}</div><div class="text-xs text-[#718499]">{{ game.company.name }} · {{ game.current_date }}</div></td><td class="px-4 py-4"><div class="text-sm">{{ game.user.name }}</div><div class="text-xs text-[#718499]">{{ game.user.email }}</div></td><td class="px-4 py-4 text-xs font-bold" :class="game.status === 'active' ? 'text-[#087c68]' : 'text-[#9a5b42]'">{{ game.status === 'active' ? 'Ativa' : game.status === 'won' ? 'Vitória' : 'Falência' }}</td><td class="px-4 py-4 text-right text-xs">{{ game.company.customers_count }} clientes · {{ game.company.employees_count }} funcionários · {{ game.company.products_count }} produtos</td><td class="px-4 py-4 text-right text-sm font-semibold">{{ formatMoney(game.company.cash_balance_cents) }}</td><td class="px-5 py-4"><div class="flex justify-end gap-1"><button title="Editar" class="grid size-9 place-items-center text-[#17638d]" @click="editGame(game)"><Pencil :size="16" /></button><button title="Excluir" class="grid size-9 place-items-center text-red-600" @click="destroyRecord('admin.games.destroy', game.id, `Excluir a partida ${game.name} e todo o seu histórico?`)"><Trash2 :size="16" /></button></div></td></tr></tbody></table>
                            <table v-if="section === 'population'" class="w-full min-w-[760px] text-left"><thead class="bg-[#f8fafc] text-xs uppercase text-[#74879a]"><tr><th class="px-5 py-3">Pessoa</th><th class="px-4 py-3">Local</th><th class="px-4 py-3 text-right">Usos no jogo</th><th class="px-5 py-3 text-right">Ações</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="person in population?.data" :key="person.id"><td class="px-5 py-4"><div class="text-sm font-semibold">{{ person.name }}</div><div class="text-xs text-[#718499]">{{ person.code }} · {{ person.email ?? 'sem e-mail' }}</div></td><td class="px-4 py-4 text-sm">{{ person.city }}/{{ person.state }}</td><td class="px-4 py-4 text-right text-xs">{{ person.customers_count }} cliente(s) · {{ person.employees_count }} funcionário(s)</td><td class="px-5 py-4"><div class="flex justify-end gap-1"><button title="Editar" class="grid size-9 place-items-center text-[#17638d]" @click="editPopulation(person)"><Pencil :size="16" /></button><button title="Excluir" class="grid size-9 place-items-center text-red-600" @click="destroyRecord('admin.population.destroy', person.id, `Excluir ${person.name}?`)"><Trash2 :size="16" /></button></div></td></tr></tbody></table>
                            <table v-if="section === 'products'" class="w-full min-w-[800px] text-left"><thead class="bg-[#f8fafc] text-xs uppercase text-[#74879a]"><tr><th class="px-5 py-3">Produto</th><th class="px-4 py-3 text-right">Preço ref.</th><th class="px-4 py-3 text-right">Custo base</th><th class="px-4 py-3 text-right">Demanda</th><th class="px-4 py-3">Status</th><th class="px-5 py-3 text-right">Ações</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="product in products?.data" :key="product.id"><td class="px-5 py-4"><div class="text-sm font-semibold">{{ product.name }}</div><div class="text-xs text-[#718499]">{{ product.sku }}</div></td><td class="px-4 py-4 text-right text-sm">{{ formatMoney(product.reference_price_cents) }}</td><td class="px-4 py-4 text-right text-sm">{{ formatMoney(product.base_cost_cents) }}</td><td class="px-4 py-4 text-right text-sm">{{ product.base_daily_demand }}</td><td class="px-4 py-4 text-xs font-bold" :class="product.active ? 'text-[#087c68]' : 'text-[#9a5b42]'">{{ product.active ? 'Ativo' : 'Inativo' }}</td><td class="px-5 py-4"><div class="flex justify-end gap-1"><button title="Editar" class="grid size-9 place-items-center text-[#17638d]" @click="editProduct(product)"><Pencil :size="16" /></button><button title="Excluir" class="grid size-9 place-items-center text-red-600" @click="destroyRecord('admin.products.destroy', product.id, `Excluir ${product.name} do catálogo futuro?`)"><Trash2 :size="16" /></button></div></td></tr></tbody></table>
                            <table v-if="section === 'job-roles'" class="w-full min-w-[850px] text-left"><thead class="bg-[#f8fafc] text-xs uppercase text-[#74879a]"><tr><th class="px-5 py-3">Cargo</th><th class="px-4 py-3 text-right">Salário</th><th class="px-4 py-3 text-right">Cap. vendas</th><th class="px-4 py-3 text-right">Cap. estoque</th><th class="px-4 py-3">Status</th><th class="px-5 py-3 text-right">Ações</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="jobRole in jobRoles?.data" :key="jobRole.id"><td class="px-5 py-4"><div class="text-sm font-semibold">{{ jobRole.name }}</div><div class="text-xs text-[#718499]">{{ jobRole.department }}</div></td><td class="px-4 py-4 text-right text-sm">{{ formatMoney(jobRole.salary_cents) }}</td><td class="px-4 py-4 text-right text-sm">{{ jobRole.sales_capacity_units }}</td><td class="px-4 py-4 text-right text-sm">{{ jobRole.inventory_capacity_units }}</td><td class="px-4 py-4 text-xs font-bold" :class="jobRole.active ? 'text-[#087c68]' : 'text-[#9a5b42]'">{{ jobRole.active ? 'Ativo' : 'Inativo' }}</td><td class="px-5 py-4"><div class="flex justify-end gap-1"><button title="Editar" class="grid size-9 place-items-center text-[#17638d]" @click="editJobRole(jobRole)"><Pencil :size="16" /></button><button title="Excluir" class="grid size-9 place-items-center text-red-600" @click="destroyRecord('admin.job-roles.destroy', jobRole.id, `Excluir o cargo ${jobRole.name}?`)"><Trash2 :size="16" /></button></div></td></tr></tbody></table>
                        </div>
                        <div v-if="records" class="flex flex-col gap-3 border-t border-[#e6edf2] px-5 py-4 text-xs text-[#718499] sm:flex-row sm:items-center sm:justify-between"><span>{{ records.from ?? 0 }}–{{ records.to ?? 0 }} de {{ records.total }}</span><div class="flex flex-wrap gap-1"><Link v-for="link in records.links" :key="link.label" :href="link.url ?? '#'" class="grid min-h-8 min-w-8 place-items-center rounded px-2 font-semibold" :class="link.active ? 'bg-[#173f67] text-white' : link.url ? 'border border-[#d7e2ea] text-[#526a80]' : 'text-[#b0bdc8]'" preserve-scroll>{{ cleanLabel(link.label) }}</Link></div></div>
                    </section>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>