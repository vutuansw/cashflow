<script setup>
// Tên một người: đã có tài khoản trong nhóm thì bấm vào ra trang thành viên, chưa có thì chỉ là chữ. Xem plan/08 mục 6.
defineProps({ name: String, memberId: Number, code: String });
</script>

<template>
    <RouterLink v-if="memberId" :to="`/${code}/thanh-vien/${memberId}`" class="hover:underline" @click.stop>{{ name }}</RouterLink>
    <span v-else>{{ name }}</span>
</template>
