<script setup>
import {useForm, usePage} from "@inertiajs/vue3";
import {computed, ref} from "vue";

const emits = defineEmits(['OnCancel', 'OnSuccess'])
const isLoading = ref(false)
const createForm = useForm({
    name: '',
    sort: 0,
    is_active: false,
    parent_id: null,
})

const page = usePage()
const departments = computed(() => page.props.departments.data)
const submit = (close) => {
    isLoading.value = true
    createForm.post('/departments/create', {
        only: ['departments', 'filters', 'flash'],
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
    createForm.reset()
}
const changeSort = (e) => {
    if (e == null) createForm.sort = 0
    createForm.sort = departments.value.filter(x => x.parent_id === e).length + 1
}
</script>

<template>
    <v-sheet class="pa-3">
        <v-toolbar class="mb-2" color="transparent">
            <h1>New Department</h1>
            <v-spacer></v-spacer>
            <v-btn icon="mdi-close" @click="emits('OnCancel')"></v-btn>
        </v-toolbar>
        <v-form @submit.prevent="submit">
            <v-autocomplete
                label="Parent"
                variant="outlined"
                density="compact"
                class="mb-2"
                v-model="createForm.parent_id"
                :items="departments"
                item-value="id"
                item-title="name"
                prepend-icon="mdi-file-tree"
                clearable
                :error-messages="createForm.errors.parent_id"
                @update:model-value="changeSort"
            ></v-autocomplete>
            <v-text-field label="Name"
                          v-model="createForm.name"
                          variant="outlined"
                          density="compact"
                          prepend-icon="mdi-rename"
                          clearable
                          :error-messages="createForm.errors.name"
            ></v-text-field>
            <v-number-input label="Sort"
                            v-model="createForm.sort"
                            variant="outlined"
                            density="compact"
                            prepend-icon="mdi-arrow-expand-up"
                            clearable
                            :error-messages="createForm.errors.sort"
            ></v-number-input>
            <v-checkbox label="Active"
                        v-model="createForm.is_active"
                        variant="outlined"
                        density="compact"
                        prepend-icon="mdi-shield-check-outline"
                        type="checked"
            ></v-checkbox>
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
