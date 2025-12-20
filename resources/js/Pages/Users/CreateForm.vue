<script setup>
import {rules} from "@/Utils/inputRules.js";

const emits = defineEmits(['OnCancel', 'OnSuccess'])

const submit = (close) => {
    console.log('submit-' + close)
    // logic create
    emits('OnSuccess', {closeForm: close})
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
                          variant="outlined"
                          density="compact"
                          prepend-icon="mdi-account"
                          :rules="[...rules.required,...rules.lowerCaseNumber]"
                          clearable
            ></v-text-field>
            <v-text-field label="Password"
                          variant="outlined"
                          density="compact"
                          prepend-icon="mdi-key"
                          :rules="[...rules.required,...rules.strongPassword]"
            ></v-text-field>
            <v-text-field label="Full name" variant="outlined" density="compact"
                          prepend-icon="mdi-card-account-details-outline"></v-text-field>

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
