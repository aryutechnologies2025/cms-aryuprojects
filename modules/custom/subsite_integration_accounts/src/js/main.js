import { createApp } from 'vue';
import Mapper from "./components/Mapper.vue";

const app = createApp({});
app.component('mapper',Mapper);
app.mount('#integrations-mapper');