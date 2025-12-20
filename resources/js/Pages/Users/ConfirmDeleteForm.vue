<script setup>

import {ref} from "vue";

const props = defineProps({
    user: Object
})
const emits = defineEmits(['OnCancel', 'OnAccept'])
const confirmText = ref(null)
</script>

<template>
    <v-card title="THÔNG BÁO" color="error">
        <template v-slot:append>
            <v-btn density="compact" variant="text" class="float-end" icon="mdi-close" @click="emits('OnCancel')"></v-btn>
        </template>
        <v-card-text class="text-center bg-white pa-4">
            Bạn đang xóa tài khoản <br>
            <v-chip>{{ user.username }}</v-chip>

            <v-text-field
                placeholder="Nhập username để xác nhận xóa"
                variant="outlined"
                density="compact"
                class="my-2"
                hide-details
                v-model="confirmText"
            ></v-text-field>
            <v-btn
                class="ml-auto w-100"
                color="error"
                text="Chấp nhận"
                variant="elevated"
                @click="emits('OnAccept')"
                :disabled="confirmText!==user.username"
            ></v-btn>
        </v-card-text>
    </v-card>
</template>

<style scoped>

</style>
