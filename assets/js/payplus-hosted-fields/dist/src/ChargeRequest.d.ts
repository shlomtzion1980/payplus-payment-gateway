declare class ChargeRequest {
    private payment_type;
    private vouchers_array?;
    private coupons_array?;
    private force_update_3d?;
    private customer?;
    private payment;
    private extra_fields?;
    constructor();
    GetData: () => any;
    SetParam: (value: any, key: string, inParam?: string) => void;
    Reset(): void;
}
declare const _default: ChargeRequest;
export default _default;
