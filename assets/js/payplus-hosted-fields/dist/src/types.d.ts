interface NonHostedFields {
    non_hosted_fields: {
        name: NonHostedFieldData;
        payments: NonHostedFieldData;
        card_holder_id: NonHostedFieldData;
        card_holder_name: NonHostedFieldData;
        card_holder_phone: NonHostedFieldData;
        card_holder_phone_prefix: NonHostedFieldData;
        custom_invoice_name: NonHostedFieldData;
        customer_name: NonHostedFieldData;
        vat_number: NonHostedFieldData;
        phone: NonHostedFieldData;
        email: NonHostedFieldData;
        contact_address: NonHostedFieldData;
        contact_country: NonHostedFieldData;
        notes: NonHostedFieldData;
    };
    extra_fields: {
        [key: string]: {
            selector: string;
            wrapperSelector: string;
            elm?: HTMLInputElement;
            error?: string;
            required?: boolean;
            uid?: string;
        };
    };
}
interface HostedFieldData {
    selector: string;
    wrapperSelector: string;
    elm?: HTMLIFrameElement;
    error?: string;
}
interface NonHostedFieldData {
    selector: string;
    wrapperSelector: string;
    elm?: HTMLInputElement;
    error?: string;
}
type ExtraFieldData = {
    (key: string): {
        selector: string;
        wrapperSelector: string;
        elm?: HTMLInputElement;
        error?: string;
    };
};
interface InitHostedFieldsData {
    cc: {
        elmSelector: string;
        wrapperElmSelector: string;
    };
    cvv: {
        elmSelector: string;
        wrapperElmSelector: string;
    };
    expiry: {
        elmSelector: string;
        wrapperElmSelector: string;
    };
    expiryy: {
        elmSelector: string;
        wrapperElmSelector: string;
    };
    expirym: {
        elmSelector: string;
        wrapperElmSelector: string;
    };
}
interface Config {
    Secure3Ds: {
        ResetStyle: boolean;
    };
}
declare enum HTMLClasses {
    IFRAME_CLASS = "__payplus_hosted_fields_item_fld-frame",
    IFRAME_CLASS_ERR = "__payplus_hosted_fields_err_fld"
}
declare enum EventNames {
    NO_ATTEMPTED_REMAINING = "pp_noAttemptedRemaining",
    PAYMENT_PAGE_KILLED = "pp_paymentPageKilled",
    PAGE_EXPIRED = "pp_pageExpired",
    RESPONSE_FROM_SERVER = "pp_responseFromServer",
    SUBMIT_PROCESS = "pp_submitProcess",
    CC_TYPE_CHANGE = "pp_ccTypeChange",
    SECURE_3DS_WINDOW = "pp_secure3dsWindow"
}
type HostedFieldsKeys = 'cc' | 'cvv' | 'expiry' | 'expiryy' | 'expirym';
declare const nonHostedFieldsMapping: any;
declare const nonHostedFieldFieldsList: string[];
declare const HostedFieldsKeysList: string[];
export { HostedFieldData, NonHostedFieldData, ExtraFieldData, EventNames, HTMLClasses, HostedFieldsKeys, NonHostedFields, nonHostedFieldsMapping, nonHostedFieldFieldsList, HostedFieldsKeysList, InitHostedFieldsData, Config };
