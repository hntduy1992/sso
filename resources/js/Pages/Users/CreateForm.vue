<script setup>
import {rules} from "@/Utils/inputRules.js";
import {router, useForm, usePage,} from "@inertiajs/vue3";
import {ref} from "vue";

const emits = defineEmits(['OnCancel', 'OnSuccess'])
const isLoading = ref(false)
const userForm = useForm({
    username: '',
    password: '',
    full_name: '',
    role: null
})

const page = usePage()
const roles = page.props.roles

const submit = (close) => {
    isLoading.value = true
    userForm.post('/users/create', {
        only: ['users,filters', 'roles', 'flash'],
        onSuccess: (res) => {
            emits('OnSuccess', {closeForm: close})
        },
        onError: (err) => {
            console.log(err)
        },
        onFinish: () => {
            isLoading.value = false
        }
    })
    userForm.reset()
}

</script>

<template>
    <v-sheet class="pa-3">
        <v-toolbar class="mb-2" color="transparent">
            <h1>New Account</h1>
            <v-spacer></v-spacer>
            <v-btn icon="mdi-close" @click="emits('OnCancel')"></v-btn>
        </v-toolbar>
        <v-form @submit.prevent="submit">
            <v-text-field label="Username"
                          v-model="userForm.username"
                          variant="outlined"
                          density="compact"
                          prepend-icon="mdi-account"
                          :rules="[...rules.required,...rules.lowerCaseNumber]"
                          :error-messages="userForm.errors?.username"
                          clearable
            ></v-text-field>
            <v-text-field label="Password"
                          v-model="userForm.password"
                          variant="outlined"
                          density="compact"
                          prepend-icon="mdi-key"
                          :rules="[...rules.required,...rules.strongPassword]"
                          :error-messages="userForm.errors?.password"
            ></v-text-field>
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
                      :error-messages="userForm.errors?.role"
            ></v-select>
            <v-toolbar color="transparent">
                <v-spacer/>
                <v-btn color="success" variant="outlined" class="mr-2" @click="submit(true)">Save and Close</v-btn>
                <v-btn color="primary" variant="elevated" @click="submit(false)">Save</v-btn>
            </v-toolbar>
        </v-form>
    </v-sheet>
</template>

<style scoped>

</style>
