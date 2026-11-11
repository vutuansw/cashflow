<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ChevronRight, Lock, ShieldCheck } from 'lucide-vue-next';
import BackButton from '../components/BackButton.vue';
import FilterBar from '../components/FilterBar.vue';
import UserAvatar from '../components/UserAvatar.vue';
import { api, ApiError } from '../lib/api.js';
import { fold } from '../lib/text.js';
import SiteFooter from '../components/SiteFooter.vue';

// Danh sách thành viên /{ma-nhom}/members, mở từ "N thành viên" ở đầu trang nhóm (như mục Thành viên của nhóm
// Facebook): ban quản trị trước, rồi mọi người; tìm theo tên không dấu. Người có tài khoản bấm vào ra trang thành
// viên. Chỉ tên, ảnh, vai trò. Xem plan/08 mục 10e.
const route = useRoute();
const code = computed(() => route.params.group);
const group = ref(null);
const people = ref(null);
const blocked = ref(false);
const search = ref('');

onMounted(async () => {
    group.value = await api(`/g/${code.value}`).catch(() => null);
    if (group.value) document.title = `Thành viên · ${group.value.name}`;
    try {
        people.value = (await api(`/g/${code.value}/members`)).data;
    } catch (e) {
        if (e instanceof ApiError && e.status === 403) blocked.value = true;
    }
});

const shown = computed(() => {
    const q = fold(search.value.trim());
    return (people.value ?? []).filter((p) => !q || fold(p.name).includes(q));
});
// Trưởng nhóm đứng đầu ban quản trị.
const admins = computed(() => shown.value.filter((p) => p.role_label).sort((a, b) => (a.role === 'owner' ? -1 : b.role === 'owner' ? 1 : 0)));
const others = computed(() => shown.value.filter((p) => !p.role_label));
const sections = computed(() => [
    { key: 'admins', title: 'Ban quản trị', items: admins.value },
    { key: 'members', title: 'Thành viên', items: others.value },
].filter((s) => s.items.length));
</script>

<template>
    <div class="mx-auto min-h-dvh max-w-xl pb-10">
        <header class="sticky top-0 z-10 flex min-h-14 items-center gap-2 border-b border-border bg-background/90 px-2 backdrop-blur">
            <BackButton :to="`/${code}`" label="trang nhóm" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs text-muted-foreground">{{ group?.name }}</p>
                <h1 class="font-semibold">Thành viên<template v-if="people"> · {{ people.length }}</template></h1>
            </div>
        </header>

        <div v-if="blocked" class="card m-4 p-6 text-center">
            <Lock class="mx-auto size-8 text-muted-foreground" />
            <p class="mt-3 font-semibold">Nhóm riêng tư</p>
            <RouterLink :to="`/login?next=/${code}/members`" class="btn-primary mt-4 w-full">Đăng nhập</RouterLink>
        </div>

        <div v-else class="space-y-4 p-4">
            <FilterBar v-model:q="search" placeholder="Tìm thành viên" />

            <p v-if="!people" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>
            <p v-else-if="!shown.length" class="card p-6 text-center text-sm text-muted-foreground">Không có ai tên "{{ search }}".</p>

            <section v-for="s in sections" :key="s.key">
                <h2 class="mb-2 px-1 text-sm font-semibold text-muted-foreground">{{ s.title }} · {{ s.items.length }}</h2>
                <ul class="card divide-y divide-border">
                    <li v-for="(p, i) in s.items" :key="p.member_handle ?? `${s.key}-${i}`">
                        <component :is="p.member_handle ? 'RouterLink' : 'div'" :to="p.member_handle ? `/${code}/members/${p.member_handle}` : undefined" class="flex items-center gap-3 p-3" :class="{ 'hover:bg-muted/60': p.member_handle }">
                            <UserAvatar :url="p.avatar_url" :name="p.name" class="!size-11 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ p.name }}</p>
                                <p v-if="p.role_label" class="flex items-center gap-1 text-xs font-medium text-primary"><ShieldCheck class="size-3.5" /> {{ p.role_label }}</p>
                            </div>
                            <ChevronRight v-if="p.member_handle" class="size-5 shrink-0 text-muted-foreground" />
                        </component>
                    </li>
                </ul>
            </section>
        </div>
        <SiteFooter class="hidden lg:block" />
    </div>
</template>
