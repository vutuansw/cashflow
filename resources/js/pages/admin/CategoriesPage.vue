<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowDown, ArrowUp, Plus } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import CategoryIcon from '../../components/CategoryIcon.vue';
import FormField from '../../components/FormField.vue';
import Sheet from '../../components/Sheet.vue';
import { adminApi } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';
import { toast } from '../../lib/notice.js';

// Loại thu chi, xem plan/03 mục 1.
const ICONS = ['piggy-bank', 'wallet', 'hand-coins', 'gift', 'utensils', 'beer', 'cake', 'flower-2', 'heart', 'hospital', 'baby', 'plane', 'bus', 'shirt', 'book-open', 'trophy', 'party-popper', 'house', 'shopping-cart', 'circle'];
const COLORS = ['#16a34a', '#0ea5e9', '#6366f1', '#a855f7', '#ec4899', '#ef4444', '#f59e0b', '#64748b'];

const route = useRoute();
const code = computed(() => route.params.group);
const categories = ref([]);
const editing = ref(null);
const form = reactive({ type: 'expense', name: '', icon: 'circle', color: '#64748b', archived: false });
const { state, submit } = useForm();

const groups = computed(() => [
    { type: 'income', title: 'Loại thu', items: categories.value.filter((c) => c.type === 'income') },
    { type: 'expense', title: 'Loại chi', items: categories.value.filter((c) => c.type === 'expense') },
]);

async function load() {
    categories.value = await adminApi(code.value, '/categories').catch(() => []);
}
onMounted(load);

function open(category = null, type = 'expense') {
    editing.value = category ?? { type };
    Object.assign(form, {
        type: category?.type ?? type,
        name: category?.name ?? '',
        icon: category?.icon ?? 'circle',
        color: category?.color ?? '#64748b',
        archived: category?.archived ?? false,
    });
}

async function save() {
    const isNew = !editing.value.id;
    const res = await submit(() => adminApi(code.value, isNew ? '/categories' : `/categories/${editing.value.id}`, { method: isNew ? 'POST' : 'PUT', body: form })).catch(ignore);
    if (!res) return;
    toast('Đã lưu.');
    editing.value = null;
    load();
}

async function remove() {
    if (!confirm(`Xóa loại "${editing.value.name}"?`)) return;
    const ok = await submit(() => adminApi(code.value, `/categories/${editing.value.id}`, { method: 'DELETE' }).then(() => true)).catch(ignore);
    if (!ok) {
        if (state.errors.category) toast(state.errors.category, 'error');
        return;
    }
    editing.value = null;
    load();
}

async function move(items, index, delta) {
    const ids = items.map((c) => c.id);
    const [id] = ids.splice(index, 1);
    ids.splice(index + delta, 0, id);
    categories.value = await adminApi(code.value, '/categories/reorder', { method: 'POST', body: { ids } });
}
</script>

<template>
    <AdminShell title="Loại thu chi">
        <section v-for="g in groups" :key="g.type" class="mb-6">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-semibold">{{ g.title }}</h2>
                <button class="btn-outline min-h-9 px-3 text-sm" @click="open(null, g.type)"><Plus class="size-4" /> Thêm</button>
            </div>
            <ul class="card divide-y divide-border">
                <li v-for="(c, i) in g.items" :key="c.id" class="flex items-center gap-3 p-3" :class="{ 'opacity-50': c.archived }">
                    <button class="flex min-w-0 flex-1 items-center gap-3 text-left" @click="open(c)">
                        <CategoryIcon :name="c.icon" :color="c.color" />
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ c.name }}</span>
                            <span class="text-sm text-muted-foreground">{{ c.archived ? 'Đang ẩn' : `${c.transactions_count} phiếu` }}</span>
                        </span>
                    </button>
                    <button class="icon-btn size-9" :disabled="i === 0" aria-label="Lên" @click="move(g.items, i, -1)"><ArrowUp class="size-4" /></button>
                    <button class="icon-btn size-9" :disabled="i === g.items.length - 1" aria-label="Xuống" @click="move(g.items, i, 1)"><ArrowDown class="size-4" /></button>
                </li>
            </ul>
        </section>

        <Sheet :open="editing !== null" :title="editing?.id ? 'Sửa loại' : (form.type === 'income' ? 'Thêm loại thu' : 'Thêm loại chi')" @close="editing = null">
            <form class="space-y-4" @submit.prevent="save">
                <FormField v-model="form.name" label="Tên loại" :error="state.errors.name" required />
                <div>
                    <span class="mb-1.5 block text-sm font-medium">Biểu tượng</span>
                    <div class="grid grid-cols-5 gap-2 sm:grid-cols-10">
                        <button v-for="icon in ICONS" :key="icon" type="button" class="grid place-items-center rounded-xl p-1" :class="form.icon === icon ? 'ring-2 ring-primary' : ''" @click="form.icon = icon">
                            <CategoryIcon :name="icon" :color="form.color" />
                        </button>
                    </div>
                </div>
                <div>
                    <span class="mb-1.5 block text-sm font-medium">Màu</span>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="color in COLORS" :key="color" type="button" class="size-9 rounded-full" :class="form.color === color ? 'ring-2 ring-offset-2 ring-primary ring-offset-card' : ''" :style="{ backgroundColor: color }" :aria-label="color" @click="form.color = color"></button>
                    </div>
                </div>
                <label v-if="editing?.id" class="flex min-h-11 items-center gap-3">
                    <input v-model="form.archived" type="checkbox" class="size-5 accent-primary" /> Ẩn loại này khỏi form tạo phiếu
                </label>
                <button class="btn-primary w-full" :disabled="state.busy">Lưu</button>
                <button v-if="editing?.id && !editing.transactions_count" type="button" class="btn-outline w-full text-expense" @click="remove">Xóa loại này</button>
            </form>
        </Sheet>
    </AdminShell>
</template>
