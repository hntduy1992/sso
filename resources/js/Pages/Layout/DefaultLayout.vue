<script setup>
import {Link, usePage} from "@inertiajs/vue3";
import {computed, ref, watch} from "vue";
import NavigationContent from "@/Pages/Layout/NavigationContent.vue";
import UserControl from "@/Pages/Layout/UserControl.vue";

const page = usePage()

const drawer = ref(true)
const isAuthenticated = computed(() => page.props.auth.user)

const flash = computed(() => page.props.flash)
const showSnackbar = ref(false);
const message = ref('');
const color = ref('success');

watch(
    () => page.props.flash, (newFlash) => {
        if (newFlash.success) {
            message.value = newFlash.success;
            color.value = 'success';
            showSnackbar.value = true;
        } else {
            message.value = newFlash.error;
            color.value = 'error';
            showSnackbar.value = true;
        }
    },
    {
        deep: true, immediate: true
    }
)
</script>

<template>
    <v-app>
        <v-navigation-drawer v-model="drawer" v-if="isAuthenticated">
            <NavigationContent/>
        </v-navigation-drawer>
        <v-app-bar v-if="isAuthenticated">
            <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>

            <v-app-bar-title>Application</v-app-bar-title>

            <UserControl/>
        </v-app-bar>
        <v-main>
            <slot/>
        </v-main>

        <v-snackbar
            v-model="showSnackbar"
            :color="color"
            elevation="24"
            location="top right"
            timeout="3000"
            v-if="message"
        >
            {{ message }}

            <template v-slot:actions>
                <v-btn variant="text" @click="showSnackbar = false">Đóng</v-btn>
            </template>
        </v-snackbar>
    </v-app>
</template>

<style scoped>

</style>
