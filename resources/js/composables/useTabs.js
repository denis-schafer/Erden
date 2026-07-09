import { ref } from 'vue';

const tabDataStore = ref(null);

export function useTabs() {
    function setTabData(data) {
        tabDataStore.value = data;
    }

    function getTabData() {
        return tabDataStore.value;
    }

    function clearTabData() {
        tabDataStore.value = null;
    }

    return { setTabData, getTabData, clearTabData };
}
