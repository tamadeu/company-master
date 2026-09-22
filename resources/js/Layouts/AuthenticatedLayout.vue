<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { PageProps } from '@/types';
import {
    Bell,
    CalendarDays,
    ChevronDown,
    CircleDollarSign,
    ContactRound,
    FileChartColumn,
    LayoutDashboard,
    LockKeyhole,
    LogOut,
    Mail,
    Menu,
    Package,
    ShieldCheck,
    ShoppingCart,
    UserRound,
    Users,
    X,
} from '@lucide/vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface LayoutGame {
    id: number;
    currentDate: string;
    dayNumber: number;
    victoryDays: number;
}

interface LayoutCompany {
    name: string;
}

interface NavigationItem {
    label: string;
    icon: typeof LayoutDashboard;
    href: string;
    active: boolean;
    badge?: number;
}

const page = usePage<
    PageProps<{
        game?: LayoutGame | null;
        company?: LayoutCompany | null;
    }>
>();
const mobileNavigationOpen = ref(false);
const currentRealDate = ref(new Date());
let dateRefreshTimer: ReturnType<typeof setTimeout> | null = null;

const game = computed(() => page.props.game ?? null);
const company = computed(() => page.props.company ?? null);
const overviewUrl = computed(() =>
    game.value ? route('games.show', game.value.id) : route('dashboard'),
);

const availableNavigation = computed<NavigationItem[]>(() => {
    const adminNavigation = page.props.auth.user.is_admin
        ? [{ label: 'Administração', icon: ShieldCheck, href: route('admin.overview'), active: route().current('admin.*') }]
        : [];
    const inboxNavigation = {
        label: 'Caixa de entrada',
        icon: Mail,
        href: route('inbox.index'),
        active: route().current('inbox.*'),
        badge: page.props.auth.unreadInboxCount,
    };

    if (!game.value) {
        return [
            { label: 'Visão geral', icon: LayoutDashboard, href: overviewUrl.value, active: route().current('dashboard') },
            inboxNavigation,
            ...adminNavigation,
        ];
    }

    return [
        { label: 'Visão geral', icon: LayoutDashboard, href: overviewUrl.value, active: route().current('games.show') },
        { label: 'Vendas', icon: CircleDollarSign, href: route('games.products.index', game.value.id), active: route().current('games.products.*') },
        { label: 'Clientes', icon: ContactRound, href: route('games.customers.index', game.value.id), active: route().current('games.customers.*') },
        { label: 'Compras', icon: ShoppingCart, href: route('games.purchases.index', game.value.id), active: route().current('games.purchases.*') || route().current('games.purchase-orders.*') },
        { label: 'Estoque', icon: Package, href: route('games.inventory.index', game.value.id), active: route().current('games.inventory.*') },
        { label: 'Financeiro', icon: CircleDollarSign, href: route('games.finance.index', game.value.id), active: route().current('games.finance.*') },
        { label: 'Relatórios', icon: FileChartColumn, href: route('games.reports.index', game.value.id), active: route().current('games.reports.*') },
        { label: 'Equipe', icon: Users, href: route('games.team.index', game.value.id), active: route().current('games.team.*') || route().current('games.employees.*') },
        inboxNavigation,
        ...adminNavigation,
    ];
});

const formattedRealDate = computed(() => {
    if (!game.value) {
        return null;
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(currentRealDate.value);
});

const scheduleDateRefresh = () => {
    currentRealDate.value = new Date();
    const nextMidnight = new Date();
    nextMidnight.setHours(24, 0, 1, 0);
    dateRefreshTimer = setTimeout(scheduleDateRefresh, nextMidnight.getTime() - Date.now());
};

onMounted(scheduleDateRefresh);
onUnmounted(() => {
    if (dateRefreshTimer) {
        clearTimeout(dateRefreshTimer);
    }
});

const unavailableNavigation: Array<{ label: string; icon: typeof CircleDollarSign }> = [];
</script>

<template>
    <div class="min-h-screen bg-[#f4f7fa] text-[#12233f]">
        <div
            v-if="mobileNavigationOpen"
            class="fixed inset-0 z-40 bg-[#071729]/55 lg:hidden"
            @click="mobileNavigationOpen = false"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-60 flex-col bg-[#102b46] text-white transition-transform duration-200 lg:translate-x-0"
            :class="mobileNavigationOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center justify-between border-b border-[#dce5ec] bg-white px-4">
                <Link :href="overviewUrl" class="flex min-w-0 items-center" aria-label="Company Master">
                    <ApplicationLogo class="h-9 w-auto max-w-[185px]" />
                </Link>
                <button
                    type="button"
                    class="grid size-9 place-items-center text-[#17324f] lg:hidden"
                    aria-label="Fechar navegação"
                    @click="mobileNavigationOpen = false"
                >
                    <X :size="20" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-5" aria-label="Navegação principal">
                <Link
                    v-for="item in availableNavigation"
                    :key="item.label"
                    :href="item.href"
                    class="flex h-11 items-center gap-3 rounded-md px-3 text-sm font-semibold transition"
                    :class="item.active ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white'"
                    @click="mobileNavigationOpen = false"
                >
                    <component :is="item.icon" :size="19" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span v-if="item.badge" class="grid min-w-5 place-items-center rounded-full bg-[#24c6b3] px-1.5 py-0.5 text-[10px] font-bold text-[#0d3049]">{{ item.badge > 99 ? '99+' : item.badge }}</span>
                </Link>

                <button
                    v-for="item in unavailableNavigation"
                    :key="item.label"
                    type="button"
                    disabled
                    class="flex h-11 w-full items-center gap-3 rounded-md px-3 text-left text-sm text-white/45"
                    :title="item.label"
                >
                    <component :is="item.icon" :size="19" />
                    <span class="flex-1">{{ item.label }}</span>
                    <LockKeyhole :size="13" />
                </button>
            </nav>

            <div class="border-t border-white/10 px-5 py-5 text-xs leading-5 text-white/45">
                Grandes decisões também constroem bons negócios.
            </div>
        </aside>

        <div class="lg:pl-60">
            <header class="sticky top-0 z-30 flex h-16 items-center border-b border-[#dce5ec] bg-white px-4 lg:px-7">
                <button
                    type="button"
                    class="mr-3 grid size-10 place-items-center rounded-md border border-[#dce5ec] text-[#17324f] lg:hidden"
                    aria-label="Abrir navegação"
                    @click="mobileNavigationOpen = true"
                >
                    <Menu :size="21" />
                </button>

                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-bold text-[#12233f]">
                        {{ company?.name ?? 'Company Master' }}
                    </div>
                    <div class="truncate text-xs text-[#6b7f93]">
                        {{ company ? 'Sua empresa, suas decisões.' : 'Simulação empresarial' }}
                    </div>
                </div>

                <div v-if="game" class="hidden items-center gap-3 border-r border-[#dce5ec] pr-6 sm:flex">
                    <CalendarDays :size="20" class="text-[#173f67]" />
                    <div>
                        <div class="text-xs font-semibold capitalize text-[#233b57]">{{ formattedRealDate }}</div>
                        <div class="text-[11px] text-[#7a8da0]">Dia {{ game.dayNumber }} de {{ game.victoryDays }}</div>
                    </div>
                </div>

                <Link :href="route('notifications.index')" class="relative ml-3 grid size-10 place-items-center rounded-md text-[#526a80] hover:bg-[#f0f5f8] hover:text-[#173f67]" :class="route().current('notifications.*') ? 'bg-[#e8f3f5] text-[#087c68]' : ''" aria-label="Notificações de vendas">
                    <Bell :size="20" />
                    <span v-if="page.props.auth.unreadSalesNotificationCount" class="absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-[#e6653f] px-1 text-[9px] font-bold leading-4 text-white">{{ page.props.auth.unreadSalesNotificationCount > 99 ? '99+' : page.props.auth.unreadSalesNotificationCount }}</span>
                </Link>

                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button type="button" class="ml-4 flex items-center gap-3 rounded-md px-2 py-1.5 text-left">
                            <span class="grid size-9 place-items-center rounded-full bg-[#173f67] text-xs font-bold text-white">
                                {{ page.props.auth.user.name.slice(0, 2).toUpperCase() }}
                            </span>
                            <span class="hidden md:block">
                                <span class="block max-w-36 truncate text-xs font-bold text-[#1d334d]">{{ page.props.auth.user.name }}</span>
                                <span class="block text-[11px] text-[#7a8da0]">{{ page.props.auth.user.is_admin ? 'Administrador' : 'Gestor' }}</span>
                            </span>
                            <ChevronDown :size="15" class="hidden text-[#7a8da0] md:block" />
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">
                            <span class="flex items-center gap-2"><UserRound :size="16" /> Perfil</span>
                        </DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">
                            <span class="flex items-center gap-2"><LogOut :size="16" /> Sair</span>
                        </DropdownLink>
                    </template>
                </Dropdown>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>