import { NonHostedFields } from "./types";
declare abstract class BaseDataInput {
    abstract fields: NonHostedFields;
    abstract SetError(key: string, obj: any, error: string): void;
    abstract SetValue(key: string, obj: any, value: any, readOnly?: boolean): void;
    abstract SetInput(key: string, obj: any): void;
    abstract HideInput(key: string, obj: any): void;
    abstract ResetInputs(): void;
    abstract GetInput(): string;
    abstract AddField(fld: string, elmSelector: string, wrapperElmSelector?: string): void;
}
export default BaseDataInput;
