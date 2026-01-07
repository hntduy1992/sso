<script setup>
import {computed, ref} from "vue";

import {Link, router, usePage} from "@inertiajs/vue3";
import CreateForm from "@/Pages/Departments/CreateForm.vue";

const page = usePage()

const props = defineProps({
    departments: Object,
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
        title: 'Name',
        align: 'start',
        key: 'name',
        sortable: false,
        headerProps: {
            class: 'font-weight-bold text-uppercase'
        }
    },
    {
        title: 'Status',
        align: 'center',
        key: 'is_active',
        sortable: false,
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
    router.delete('/departments/' + Item.value.id + '/delete', {
        only: ['departments', 'filters', 'flash'],
        onSuccess: (res) => {
            deleteDialog.value = false
        }
    })
}
const deleteItemsHandler = () => {
    router.delete('/departments/deletes', {
        data: {
            ids: selected.value?.map(x => x.id)
        },
        only: ['departments', 'filters', 'flash'],
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
    router.visit('/departments', {
        only: ['departments', 'filters'],
        data: {
            search: filters.value.search,
            perPage: filters.value.perPage,
            page: e,
        }
    })
}
const goSearch = () => {
    router.visit('/departments', {
        only: ['departments', 'filters'],
        data: {
            search: filters.value.search,
            perPage: filters.value.perPage,
        }
    })
}
const changePerPage = (e) => {
    router.visit('/departments', {
        only: ['departments', 'filters'],
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
            Departments manager
            <v-btn prepend-icon="mdi-plus" color="success"
                   class="float-end " @click="createDrawer = true">Add Departments
            </v-btn>
            <v-badge v-if="selected.length>0" class="mr-2 float-end" :content="selected.length">
                <v-btn prepend-icon="mdi-delete" color="error" @click="deleteMultiDialog=true">Delete</v-btn>
            </v-badge>
            <span class=""></span>
        </v-card-title>
        <v-card-text>
            <v-data-table-server
                :headers="headers"
                :items="props.departments.data"
                :items-length="props.departments.total"
                :page="filters.page"
                :items-per-page="filters.perPage"
                @update:page="gotoPage"
                @update:itemsPerPage="changePerPage"
                show-select
                v-model="selected"
                return-object
                :hide-default-footer="props.departments.total<10"
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

                <template v-slot:item.is_active="{ item }">
                    <v-chip

                        :color="item.is_active?'green':'red'"
                    >
                        {{ item.is_active ? 'Activated' : 'In active' }}
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
                                    title="View Users"
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
        <!--        <UpdateInfo v-if="updateDrawer" :User="Item" @OnSuccess="updateSubmit"></UpdateInfo>-->
    </v-navigation-drawer>
    <v-dialog v-if="deleteDialog" v-model="deleteDialog" max-width="340">
        <!--        <ConfirmDeleteForm :user="Item" @OnCancel="deleteDialog=false"-->
        <!--                           @OnAccept="deleteItemHandler"></ConfirmDeleteForm>-->
    </v-dialog>
    <v-dialog v-if="deleteMultiDialog" v-model="deleteMultiDialog" max-width="340">
        <!--        <ConfirmDeleteForm :users="selected" @OnCancel="deleteMultiDialog=false"-->
        <!--                           @OnAccept="deleteItemsHandler"></ConfirmDeleteForm>-->
    </v-dialog>
</template>

<style scoped>

</style>
