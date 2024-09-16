import BaseDataInput from "./BaseDataInput";
import { NonHostedFields } from "./types";
declare class Dom extends BaseDataInput {
    fields: NonHostedFields;
    constructor();
    SetError(key: string, obj: any, error: string): void;
    SetValue(key: string, obj: any, value: any, readOnly?: boolean): void;
    SetInput(key: string, obj: any): void;
    HideInput(key: string, obj: any): void;
    ResetInputs(): void;
    GetInput(): any;
    AddField(fld: string, elmSelector: string, wrapperElmSelector?: string): void;
}
export default Dom;
