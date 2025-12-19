<script setup>
import {can} from "@/Utils/permissions.js";

import {Link, router, usePage} from "@inertiajs/vue3";

const page = usePage()
</script>

<template>
    <v-list>
        <v-list-item link @click="router.visit('/dashboard')" prepend-icon="mdi-view-dashboard">Dashboard</v-list-item>
        <v-list-item v-if="can('manage users')"  @click="router.visit('/users')"
                     prepend-icon="mdi-account-group">Accounts
        </v-list-item>
    </v-list>
</template>

<style scoped>

</style>
