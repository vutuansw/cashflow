<script setup>
import { ImageUp, Maximize2, Trash2 } from 'lucide-vue-next';
import FormField from './FormField.vue';
import Sheet from './Sheet.vue';
import { session } from '../lib/session.js';

// Phần giao diện của useProfileEditor (lib/profileEditor.js): input chọn ảnh ẩn, menu ảnh Xem / Đổi / Gỡ,
// sheet Sửa thông tin. Chỉ gắn vào trang thành viên khi người xem là chủ trang (plan/24).
const props = defineProps({ editor: { type: Object, required: true } });
const e = props.editor;
</script>

<template>
    <input :ref="(el) => (e.inputs.avatar.value = el)" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="e.upload('avatar', $event)" />
    <input :ref="(el) => (e.inputs.cover.value = el)" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="e.upload('cover', $event)" />

    <!-- Menu ảnh: Xem / Đổi / Gỡ -->
    <Sheet :open="e.imageMenu.value !== null" :title="e.imageMenu.value === 'cover' ? 'Ảnh bìa' : 'Ảnh đại diện'" @close="e.imageMenu.value = null">
        <div class="grid gap-3">
            <button type="button" class="btn-outline" @click="e.view(e.imageMenu.value)"><Maximize2 class="size-4" /> Xem ảnh</button>
            <button type="button" class="btn-outline" @click="e.pick(e.imageMenu.value)"><ImageUp class="size-4" /> Chọn ảnh khác</button>
            <button type="button" class="btn-outline text-expense" @click="e.removeImage(e.imageMenu.value)"><Trash2 class="size-4" /> Gỡ ảnh</button>
        </div>
        <p class="mt-3 text-sm text-muted-foreground">{{ e.imageMenu.value === 'cover' ? 'Ảnh bìa được cắt theo tỉ lệ 3:1 ở giữa ảnh.' : 'Ảnh đại diện được cắt vuông ở giữa ảnh.' }}</p>
    </Sheet>

    <!-- Sửa thông tin -->
    <Sheet :open="e.editing.value" title="Sửa thông tin" @close="e.editing.value = false">
        <form class="space-y-4" @submit.prevent="e.save">
            <FormField v-model="e.form.name" label="Họ tên" :error="e.state.errors.name" required maxlength="120" />
            <FormField label="Giới thiệu" :error="e.state.errors.bio" :hint="`${e.form.bio.length}/160 · Hiện ở mục Giới thiệu trên trang của bạn.`">
                <textarea v-model="e.form.bio" class="field min-h-20" :class="{ 'field-error': e.state.errors.bio }" maxlength="160" rows="3" placeholder="Vd: Thủ quỹ chi đoàn A12"></textarea>
            </FormField>
            <FormField v-model="e.form.email" label="Email" type="email" hint="Để lấy lại mật khẩu khi quên. Không hiện cho người khác." :error="e.state.errors.email" />
            <FormField label="Số điện thoại" hint="Muốn đổi số, nhờ trưởng nhóm liên hệ quản trị WePo. Không hiện cho người khác.">
                <input class="field" :value="session.me?.phone" disabled />
            </FormField>
            <button class="btn-primary w-full" :disabled="e.state.busy">Lưu</button>
        </form>
    </Sheet>
</template>
