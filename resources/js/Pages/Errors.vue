<script setup>
import { computed } from 'vue'
import {router} from "@inertiajs/vue3";

const props = defineProps({ status: Number })

const errorContent = computed(() => {
    return {
        503: { title: '503: Service Unavailable', description: 'Hệ thống đang bảo trì, vui lòng quay lại sau.', icon: 'mdi-cog-sync' },
        500: { title: '500: Server Error', description: 'Đã có lỗi xảy ra trên server hệ thống.', icon: 'mdi-alert-octagon' },
        404: { title: '404: Page Not Found', description: 'Trang bạn tìm kiếm không tồn tại.', icon: 'mdi-magnify-minus' },
        403: { title: '403: Forbidden', description: 'Bạn không có quyền truy cập trang này.', icon: 'mdi-lock-alert' },
    }[props.status] || { title: 'Error', description: 'Đã có lỗi xảy ra.', icon: 'mdi-alert' }
})
</script>

<template>
    <v-container class="fill-height  flex-column justify-center text-center">
        <v-card class="ma-3" elevation="10">
            <v-card-text>
                <v-icon size="100" color="error" class="mb-4">{{ errorContent.icon }}</v-icon>
                <h1 class="text-h3 mb-2">{{ errorContent.title }}</h1>
                <p class="text-body-1 mb-6 text-grey-darken-1">{{ errorContent.description }}</p>
            </v-card-text>
        </v-card>

        <v-btn color="primary" prepend-icon="mdi-home" @click="router.visit('/')">
            Quay lại
        </v-btn>
    </v-container>
</template>
