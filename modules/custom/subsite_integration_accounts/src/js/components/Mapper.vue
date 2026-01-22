<script setup>
import {ref, onMounted, computed} from 'vue'

const props = defineProps(
    {
        formTemplate: {
            type: Object,
            default: "",
            required: true
        },
        defaultValues: {
            type: [String, null],
            default: "",
            required: true
        },
        buttonTitle: {
            type: String,
            default: "Add New API",
            required: false
        }
    }
)

const panels = ref([]);
let mappedSettings = "";

onMounted(() => {
    mappedSettings = document.getElementById('mapped-fields');
    if (props.defaultValues) {
        panels.value = parseDefaultValues(props.defaultValues);
        updateMappedFields();
    }
});

const elClasses = {
    formItem: "js-form-item form-item js-form-type-textfield form-type--textfield js-form-item-label form-item--label",
    formText: "form-text form-element form-element--type-text form-element--api-textfield",
    fieldset: "fieldset js-form-item form-item js-form-wrapper form-wrapper"
};
const addNewPanel = () => {
    panels.value.push(JSON.parse(JSON.stringify(props.formTemplate)));
    updateMappedFields();
}

const removePanel = (panel) => {
    panels.value.splice(panel, 1);
    updateMappedFields();
}

const parseDefaultValues = (defaultValues) => {
    let obj= JSON.parse(defaultValues);
    let parsed = [];
    for(const [key,value] of Object.entries(obj)) {
        parsed.push(value);
	}
    return parsed;
}

const updateMappedFields = () => {
    mappedSettings.value = encodeURIComponent(JSON.stringify(panels.value));
}
</script>

<template>
    <div id="settings">
        <div id="panels">
            <fieldset
                    :id="`panel-${index}`"
                    :class="elClasses.fieldset"
                    v-for="(panel,index) in panels" :key="index"
            >
                <div class="fieldset__wrapper">
                    <div
                            :class="elClasses.formItem"
                            v-for="(item,key) in panel"
                            :key="`${index}-${key}-${item.id}`"
                    >
                        <label class="form-item__label">{{ item.name }}</label>
                        <input v-model="panels[index][key].value"
                               type="text" name="label"
                               :id="`panel-${index}-${item.id}`"
                               :class="elClasses.formText"
                               @change="updateMappedFields"
                        />
                    </div>
                    <div class="form-actions js-form-wrapper form-wrapper">
                        <button
                                v-on:click="removePanel(index)"
                                id="remove-panel"
                                type="button"
                                class="action-link action-link--danger action-link--icon-trash remove-button"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </fieldset>
        </div>
        <button
                v-on:click="addNewPanel"
                id="add-panel"
                class="button"
                type="button"
        >
            {{ props.buttonTitle }}
        </button>
    </div>

</template>

<style scoped>

</style>