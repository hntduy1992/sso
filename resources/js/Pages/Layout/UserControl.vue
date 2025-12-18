<script setup>
import {Link, usePage} from "@inertiajs/vue3";
import {ref} from "vue";

const page = usePage()
const menu = ref(false)
</script>

<template>
    <v-menu
        v-model="menu"
        :close-on-content-click="false"
        location="bottom right"
    >
        <template v-slot:activator="{ props }">
            <v-avatar image=""></v-avatar>
            <span
                v-bind="props"
            >
                {{ page.props.auth.user.full_name}}
            </span>
        </template>

        <v-card min-width="250">
            <v-list>
                <v-list-item :tag="Link" link href="/logout">Logout
                </v-list-item>
            </v-list>
        </v-card>
    </v-menu>
</template>

<style scoped>

</style>
