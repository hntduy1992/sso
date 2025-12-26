<script setup>

import {computed, ref} from "vue";

const props = defineProps({
    user: Object,
    users: Object
})
const emits = defineEmits(['OnCancel', 'OnAccept'])
const confirmText = ref(null)
const confirmPercent = ref(0)
const sliderColor = computed(() => {
    if (confirmPercent.value < 20) return 'green'
    if (confirmPercent.value < 40) return 'default'
    if (confirmPercent.value < 60) return 'yellow'
    if (confirmPercent.value < 80) return 'orange'
    if (confirmPercent.value < 80) return 'red'
    return 'red'
})
const satisfactionEmojis = ['😍', '😄', '😁', '😊', '🙂', '😐', '🙁', '☹️', '😢', '😭']
const currentEmoji = computed(() => {
    const index = Math.min(Math.floor(confirmPercent.value / 10), 9)
    return satisfactionEmojis[index]
})
</script>

<template>
    <v-card title="THÔNG BÁO" color="error">
        <template v-slot:append>
            <v-btn density="compact" variant="text" class="float-end" icon="mdi-close"
                   @click="emits('OnCancel')"></v-btn>
        </template>
        <v-card-text v-if="props.user" class="text-center bg-white pa-4">
            Bạn đang xóa tài khoản <br>
            <v-chip>{{ props.user.username }}</v-chip>

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
                :disabled="confirmText!==props.user.username"
            ></v-btn>
        </v-card-text>
        <v-card-text v-if="props.users" class="text-center bg-white pa-4">
            Bạn đang xóa tài khoản <br>
            <v-chip class="mx-1 my-2" v-for="item of props.users">{{ item.username }}</v-chip>

            <div>
                <div class="text-caption">
                    Kéo qua phải để xác nhận
                </div>
                <v-slider
                    v-model="confirmPercent"
                    thumb-label="always"
                    :color="sliderColor"
                    prepend-icon="mdi-account-multiple-outline"
                    append-icon="mdi-delete-empty"
                >
                    <template v-slot:thumb-label="{ modelValue }">
                        {{currentEmoji }}
                    </template>
                </v-slider>
            </div>
            <v-btn
                class="ml-auto w-100"
                color="error"
                text="Chấp nhận"
                variant="elevated"
                :disabled="confirmPercent<100"
                @click="emits('OnAccept')"
            ></v-btn>
        </v-card-text>
    </v-card>
</template>

<style scoped>

</style>
