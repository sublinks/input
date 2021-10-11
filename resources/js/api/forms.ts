import { AxiosResponse } from "axios";
import handler from "./handler"

export function callCreateForm(): Promise<AxiosResponse<FormModel>> {
    return new Promise(async (resolve, reject) => {
        try {
            let response = await handler.post(window.route('api.forms.create'))
            resolve(response)
        } catch (error) {
            reject(error)
        }
    });
}