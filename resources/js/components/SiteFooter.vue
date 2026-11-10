<script setup>
// Chân trang nhỏ cho các trang đăng nhập, đăng ký, tạo nhóm: "WePo © năm - Tài trợ bởi HIPOTECH".
const year = new Date().getFullYear();
</script>

<template>
    <footer class="pt-10 text-center text-xs text-muted-foreground">
        <RouterLink to="/" class="font-semibold text-foreground hover:text-primary hover:underline">WePo</RouterLink> © {{ year }} - Tài trợ bởi
        <a href="https://hipotech.vn" target="_blank" rel="noopener" class="font-semibold text-foreground hover:text-primary hover:underline">HIPOTECH</a>
    </footer>
</template>
