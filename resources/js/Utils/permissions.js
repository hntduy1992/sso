import {usePage} from "@inertiajs/vue3";

export function can(permission) {
    const permissions = usePage().props.auth.user?.permissions || [];
    return permissions.includes(permission)
}
