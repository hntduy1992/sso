<script setup>
defineProps({
    users: Object,
    filters: Object
})
</script>

<template>
    <h1>Users index page</h1>
    <pre>{{users}}</pre>
    <pre>{{filters}}</pre>
</template>

<style scoped>

</style>
