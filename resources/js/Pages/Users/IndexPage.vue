<script setup>
import {ref} from "vue";
import CreateForm from "@/Pages/Users/CreateForm.vue";
import ConfirmDeleteForm from "@/Pages/Users/ConfirmDeleteForm.vue";
import {Link, router} from "@inertiajs/vue3";
import UpdateInfo from "@/Pages/Users/UpdateInfo.vue";

const props = defineProps({
    users: Object,
    filters: Object
})


const selected = ref([])

const headers = ref([
    {
        title: 'STT',
        width: '100',
        key: 'index',
        sortable: false,
        headerProps: {
            class: 'font-weight-bold text-uppercase'
        }
    },
    {
        title: 'Full name',
        align: 'start',
        key: 'full_name',
        sortable: false,
        headerProps: {
            class: 'font-weight-bold text-uppercase'
        }
    },
    {
        title: 'Username',
        align: 'center',
        key: 'username',
        sortable: false,
        headerProps: {
            class: 'font-weight-bold text-uppercase'
        }
    },
    {
        title: 'Roles',
        align: 'center',
        sortable: false,
        key: 'roles',
        headerProps: {
            class: 'font-weight-bold text-uppercase'
        }
    },
    {
        title: 'Actions',
        align: 'center',
        width: '75',
        sortable: false,
        key: 'actions',
        headerProps: {
            class: 'font-weight-bold text-uppercase'
        }
    },
])

const createDrawer = ref(false)
const updateDrawer = ref(false)

const filters = ref({
    search: props.filters.search || null,
    page: props.filters.page || 1,
    perPage: props.filters.perPage || 10,
})

const Item = ref(null)

const updateItem = (item) => {
    Item.value = item
    updateDrawer.value = true
}
const deleteItem = (item) => {
    Item.value = item
    deleteDialog.value = true
}
const deleteMultiDialog = ref(false)
const deleteDialog = ref(false)
const deleteItemHandler = () => {
    router.delete('/users/' + Item.value.id + '/delete', {
        only: ['users', 'filters', 'flash'],
        onSuccess: (res) => {
            deleteDialog.value = false
        }
    })
}
const deleteItemsHandler = () => {
    router.delete('/users/deletes', {
        data: {
            ids: selected.value?.map(x => x.id)
        },
        only: ['users', 'filters', 'flash'],
        onSuccess: (res) => {
            selected.value = []
            deleteMultiDialog.value = false
        }
    })
}

const createSubmit = (closeForm) => {
    createDrawer.value = !closeForm.closeForm
}
const updateSubmit = () => {
    updateDrawer.value = false
}
const isLoading = ref(false)
const gotoPage = (e) => {
    router.visit('/users', {
        only: ['users', 'filters'],
        data: {
            search: filters.value.search,
            perPage: filters.value.perPage,
            page: e,
        }
    })
}
const goSearch = () => {
    router.visit('/users', {
        only: ['users', 'filters'],
        data: {
            search: filters.value.search,
            perPage: filters.value.perPage,
        }
    })
}
const changePerPage = (e) => {
    router.visit('/users', {
        only: ['users', 'filters'],
        data: {
            search: filters.value.search,
            perPage: e,
        }
    })
}

</script>

<template>
    <v-card>
        <v-card-title class="bg-primary">
            User manager
            <v-btn prepend-icon="mdi-plus" color="success" class="float-end " @click="createDrawer = true">Add Account
            </v-btn>
            <v-badge v-if="selected.length>0" class="mr-2 float-end" :content="selected.length">
                <v-btn prepend-icon="mdi-delete" color="error" @click="deleteMultiDialog=true">Delete</v-btn>
            </v-badge>
            <span class=""></span>
        </v-card-title>
        <v-card-text>
            <v-data-table-server
                :headers="headers"
                :items="props.users.data"
                :items-length="props.users.total"
                :page="filters.page"
                :items-per-page="filters.perPage"
                @update:page="gotoPage"
                @update:itemsPerPage="changePerPage"
                show-select
                v-model="selected"
                return-object
            >
                <template v-slot:top>
                    <v-text-field v-model="filters.search"
                                  class="mt-2"
                                  label="Search"
                                  placeholder="Please input text to search..."
                                  variant="outlined"
                                  density="compact" clearable hide-details>
                        <template v-slot:append>
                            <v-btn
                                prepend-icon="mdi-magnify"
                                color="primary"
                                @click="goSearch"
                            >Tìm
                            </v-btn>
                        </template>
                    </v-text-field>

                </template>

                <template v-slot:item.index="{item,index}">{{ index + 1 }}</template>
                <template v-slot:item.full_name="{ item }">
                    <div class="d-flex align-center py-2">
                        <v-avatar v-if="item.avatar" :image="item.avatar" color="primary" size="32" class="mr-3">
                        </v-avatar>
                        <v-avatar v-else color="primary" size="32" class="mr-3">
                            <span class="text-caption">{{ item.full_name.charAt(0) }}</span>
                        </v-avatar>
                        <div class="font-weight-bold">{{ item.full_name }}</div>
                    </div>
                </template>

                <template v-slot:item.roles="{ item }">
                    <v-chip
                        v-for="role in item.roles"
                        :key="role.id"
                        size="x-small"
                        class="mr-1"
                        label
                    >
                        {{ role.name }}
                    </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                    <v-btn density="compact" variant="text" icon="mdi-dots-horizontal">
                        <v-icon></v-icon>

                        <v-menu activator="parent" location="bottom end" offset="4">
                            <v-list
                                bg-color="surface-light"
                                class="d-flex flex-column ga-1 pa-1"
                                density="compact"
                                rounded="lg"
                                variant="text"
                                slim
                            >
                                <v-list-item
                                    rounded="lg"
                                    title="Edit"
                                    link
                                    @click="updateItem(item)"
                                >
                                    <template v-slot:prepend>
                                        <v-icon color="warning">mdi-pencil</v-icon>
                                    </template>
                                </v-list-item>

                                <v-list-item
                                    rounded="lg"
                                    title="Reset password"
                                    link
                                >
                                    <template v-slot:prepend>
                                        <v-icon color="info">mdi-key-alert</v-icon>
                                    </template>
                                </v-list-item>

                                <v-list-item
                                    rounded="lg"
                                    link
                                    title="Delete"
                                    @click="deleteItem(item)"
                                >
                                    <template v-slot:prepend>
                                        <v-icon color="error">mdi-delete</v-icon>
                                    </template>

                                </v-list-item>
                            </v-list>
                        </v-menu>
                    </v-btn>
                </template>
            </v-data-table-server>
        </v-card-text>
    </v-card>
    <v-navigation-drawer
        v-model="createDrawer"
        location="right"
        temporary
        width="500"
        scrim="black"
    >
        <CreateForm @OnCancel="createDrawer=false" @OnSuccess="createSubmit"></CreateForm>
    </v-navigation-drawer>

    <v-navigation-drawer
        v-model="updateDrawer"
        location="right"
        temporary
        width="500"
        scrim="black"
    >
        <UpdateInfo v-if="updateDrawer" :User="Item" @OnSuccess="updateSubmit"></UpdateInfo>
    </v-navigation-drawer>
    <v-dialog v-if="deleteDialog" v-model="deleteDialog" max-width="340">
        <ConfirmDeleteForm :user="Item" @OnCancel="deleteDialog=false"
                           @OnAccept="deleteItemHandler"></ConfirmDeleteForm>
    </v-dialog>
    <v-dialog v-if="deleteMultiDialog" v-model="deleteMultiDialog" max-width="340">
        <ConfirmDeleteForm :users="selected" @OnCancel="deleteMultiDialog=false"
                           @OnAccept="deleteItemsHandler"></ConfirmDeleteForm>
    </v-dialog>

</template>

<style scoped>

</style>
