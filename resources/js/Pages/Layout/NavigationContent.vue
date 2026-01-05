<script setup>
import {can} from "@/Utils/permissions.js";

import {Link, router, usePage} from "@inertiajs/vue3";

const page = usePage()

const test = () => {
    router.put('/users/5/update', {
            username: 'test',
            password: 'a1234567@A',
            full_name: 'test user hacking',
            role: 1
        },
        {
            onSuccess: (res) => {
                console.log(res)
            },
            onError: (err) => {
                console.log(err)
            },
        }
    )
}

const testDelete = () => {
    router.delete('/users/1/delete')
}
const testPermission = () => {
    router.visit('/users')
}
const testCreate = () => {
    router.put('/users/5/update', {
            id:5,
            username: 'test admin 2',
            password: 'a1234567@A',
            full_name: 'test user hacking',
            role:1
        },
        {
            onSuccess: (res) => {
                console.log(res)
            },
            onError: (err) => {
                console.log(err)
            },
        }
    )
}
</script>

<template>
    <v-list>
        <v-list-item link @click="router.visit('/dashboard')" prepend-icon="mdi-view-dashboard">Dashboard</v-list-item>
        <v-list-item v-if="can('user.list')" @click="router.visit('/users')"
                     prepend-icon="mdi-account-group">Accounts
        </v-list-item>
        <v-list-item @click="testPermission">Test view permission
        </v-list-item>
        <v-list-item @click="testCreate">Test create permission
        </v-list-item>
    </v-list>
</template>

<style scoped>

</style>
