<script setup>
import {ref} from "vue";
import {useForm} from "@inertiajs/vue3";

const showPassword = ref(false);

// Sử dụng helper useForm của Inertia
const form = useForm({
    username: '',
    password: '',
});
const accounts = [
    {username: 'super_admin', password: 'admin', text: 'Super Administrator'},
    {username: 'admin', password: 'admin', text: 'Administrator'},
    {username: 'user', password: 'user', text: 'User'},
]
const submit = () => {
    form.post('/login', {
        onSuccess: () => {
        },
        onFinish: () => {
            form.reset('password')
        }
    })
};
const submitTest = (account) => {
    form.username = account.username
    form.password = account.password
    form.post('/login', {
        onSuccess: () => {
        },
        onFinish: () => {
            form.reset('password')
        }
    })
};
</script>

<template>
    <v-container fluid class="bg-grey-lighten-4 h-100" >

        <div class="d-flex justify-center flex-column h-100">
            <div class="flex-grow-1 d-flex flex-column align-center justify-center h-100">
                <div class="text-center mb-6">
                    <h1 class="text-h4 font-weight-bold text-primary">SSO ADMIN</h1>
                    <p class="text-subtitle-1 text-grey">Hệ thống xác thực tập trung</p>
                </div>
                <v-card class="elevation-12 w-100 w-lg-25 w-md-50 w-sm-75"  rounded="lg"  >
                    <v-card-text>
                        <v-alert density="compact" class="mb-4 text-center" v-if="form.errors.message" color="error">
                            {{ form.errors.message }}
                        </v-alert>
                        <v-form @submit.prevent="submit">
                            <v-text-field
                                v-model="form.username"
                                label="Username"
                                prepend-inner-icon="mdi-account"
                                variant="outlined"
                                density="comfortable"
                                class="mb-2"
                                :error-messages="form.errors.username"
                                :disabled="form.processing"
                            ></v-text-field>

                            <v-text-field
                                v-model="form.password"
                                label="Mật khẩu"
                                prepend-inner-icon="mdi-lock"
                                :append-inner-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
                                :type="showPassword ? 'text' : 'password'"
                                variant="outlined"
                                density="comfortable"
                                @click:append-inner="showPassword = !showPassword"
                                :error-messages="form.errors.password"
                                :disabled="form.processing"
                            ></v-text-field>
                            <v-btn
                                type="button"
                                color="primary"
                                block
                                size="large"
                                class="mt-4"
                                :loading="form.processing"
                                rounded="pill"
                                @click="submit"
                            >
                                Đăng nhập
                            </v-btn>
                            <v-btn
                                type="button"
                                color="error"
                                block
                                size="large"
                                class="mt-4"
                                :loading="form.processing"
                                rounded="pill"
                                v-for="account of accounts"
                                @click="submitTest(account)"
                            >
                                {{ account.text }}
                            </v-btn>
                        </v-form>
                    </v-card-text>
                </v-card>
            </div>
            <p class="text-center mt-6 text-caption text-grey">
                &copy; 2025 SSO System. Powered by Laravel 12 & Inertia.
            </p>
        </div>
    </v-container>
</template>

<style scoped>

</style>
