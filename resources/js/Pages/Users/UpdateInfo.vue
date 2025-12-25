<script setup>

import {computed, onMounted, ref, shallowRef} from "vue";
import {router, useForm, usePage} from "@inertiajs/vue3";

const props = defineProps({
    User: Object
})

const emits = defineEmits(['OnCancel', 'OnSuccess'])
const isLoading = ref(false)

const page = usePage()
const roles = page.props.roles

const userForm = useForm({
    id: props.User.id,
    username: props.User.username,
    full_name: props.User.full_name,
    role: props.User.roles && props.User.roles.length > 0 ? props.User.roles[0].id : null
})
const submit = () => {
    isLoading.value = true
    userForm.put('/users/' + props.User.id + '/update', {
        only: ['users', 'flash'],
        onSuccess: (res) => {
            console.log(res)
            emits('OnSuccess')
        },
        onError: (err) => {
            console.log(err)
        },
        onFinish: () => {
            isLoading.value = false
        }
    })
}
</script>

<template>
    <v-sheet class="pa-3">
        <v-toolbar class="mb-2" color="transparent">
            <h1>Info Account</h1>
            <v-spacer></v-spacer>
            <v-btn icon="mdi-close" @click="emits('OnCancel')"></v-btn>
        </v-toolbar>
        <v-form @submit.prevent="submit">
            <v-text-field label="Username"
                          v-model="props.User.username"
                          variant="outlined"
                          density="compact"
                          prepend-icon="mdi-account"
                          :readonly="true"></v-text-field>
            <v-text-field label="Full name"
                          v-model="userForm.full_name"
                          variant="outlined"
                          density="compact"
                          prepend-icon="mdi-card-account-details-outline"></v-text-field>
            <v-select label="Vai trò"
                      v-model="userForm.role"
                      :items="roles"
                      item-value="id"
                      item-title="name"
                      variant="outlined"
                      density="compact"
                      prepend-icon="mdi-security"
                      :error-messages="userForm.errors.role"
            ></v-select>
            <v-toolbar color="transparent">
                <v-spacer/>
                <v-btn class="mr-2">Reset password</v-btn>
                <v-btn color="success" variant="elevated"
                       :disabled="props.User.roles[0]?.id===userForm.role && props.User.full_name===userForm.full_name"
                       @click="submit"
                >Save
                </v-btn>

            </v-toolbar>
        </v-form>
    </v-sheet>
</template>

<style scoped>

</style>
