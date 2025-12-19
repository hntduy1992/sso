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
const submit = (account) => {

    if (account) {
        form.username = account.username;
        form.password = account.password;
    }

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
    <Head title="Đăng nhập SSO"/>

    <v-container fluid class="fill-height bg-grey-lighten-4">
        <v-row align="center" justify="center">
            <v-col cols="12" sm="8" md="4" lg="3">

                <div class="text-center mb-6">
                    <h1 class="text-h4 font-weight-bold text-primary">SSO ADMIN</h1>
                    <p class="text-subtitle-1 text-grey">Hệ thống xác thực tập trung</p>
                </div>

                <v-card class="elevation-12 pa-4" rounded="lg">
                    <v-card-text>
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
                                color="error"
                                block
                                size="large"
                                class="mt-4"
                                :loading="form.processing"
                                rounded="pill"
                                @click="submit({username:'super_admin',password:'admin'})"
                            >
                                Đăng nhập Super Admin
                            </v-btn>
                            <v-btn
                                type="button"
                                color="warning"
                                block
                                size="large"
                                class="mt-4"
                                :loading="form.processing"
                                rounded="pill"
                                @click="submit({username:'admin',password:'admin'})"
                            >
                                Đăng nhập Admin
                            </v-btn>
                            <v-btn
                                type="button"
                                color="info"
                                block
                                size="large"
                                class="mt-4"
                                :loading="form.processing"
                                rounded="pill"
                                @click="submit({username:'user',password:'user'})"
                            >
                                Đăng nhập User
                            </v-btn>

                        </v-form>
                    </v-card-text>
                </v-card>

                <p class="text-center mt-6 text-caption text-grey">
                    &copy; 2025 SSO System. Powered by Laravel 12 & Inertia.
                </p>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
