<script setup>

import {Link} from "@inertiajs/vue3";
</script>

<template>
    <v-list>
        <v-list-item link :tag="Link" href="/dashboard" prepend-icon="mdi-view-dashboard">Dashboard</v-list-item>
        <v-list-item link :tag="Link" href="/users" active-class="bg-red" prepend-icon="mdi-account-group">Accounts</v-list-item>
    </v-list>
</template>

<style scoped>

</style>
