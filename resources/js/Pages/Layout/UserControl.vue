<script setup>
import {Link, router, useForm, usePage} from "@inertiajs/vue3";
import {ref} from "vue";

const page = usePage()
const menu = ref(false)
const dialogLogout = ref(false)
const logout = () => {
    dialogLogout.value = true
}

const acceptLogout = () => {
    router.post('/logout')
}
</script>

<template>
    <v-menu
        v-model="menu"
        :close-on-content-click="false"
        location="bottom right"
    >
        <template v-slot:activator="{ props }">
            <v-avatar v-bind="props"
                      image="https://cdn.kienthuc.net.vn/images/cf739f51f3276a5be16e9cbb75eb6705596ce952331a30fe441dfc69aeecd85da4285c4d773aa45ecd9bef5e5365f16768afc01644256a2941c2a3bb984643c9904d34340820cfc0a7ebca9928073e3a6888185b7bb6986113ab90d80533ccad9970504b0fcd1f8446b57fd4b2179535/ngam-dan-hot-girl-viet-nam-co-ty-le-co-the-dep-hoan-hao-Hinh-2.jpg"></v-avatar>
        </template>

        <v-card min-width="250">
            <v-list>
                <v-list-item prepend-icon="mdi-shield-account-outline"
                             :subtitle="page.props.auth.user.roles[0]??null">{{ page.props.auth.user.full_name }}
                </v-list-item>
                <v-list-item prepend-icon="mdi-card-account-details">Change Profile</v-list-item>
                <v-list-item :tag="Link" link @click="logout" prepend-icon="mdi-logout">Logout</v-list-item>
            </v-list>
        </v-card>
    </v-menu>

    <v-dialog
        v-model="dialogLogout"
        width="auto"
    >
        <v-card
            max-width="400"
        >
            <v-card-title class="bg-error text-center">
                THÔNG BÁO
            </v-card-title>
            <v-card-text>
                <span>Bạn muốn đăng xuất khỏi hệ thống!</span>
            </v-card-text>
            <v-card-actions>
                <v-btn
                    class="ms-auto"
                    text="Hủy"
                    @click="dialogLogout = false"
                    width="150"
                ></v-btn>
                <v-btn
                    class="ms-auto"
                    text="Đăng xuất"
                    color="error"
                    @click="acceptLogout"
                    width="150"
                ></v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>

</style>
