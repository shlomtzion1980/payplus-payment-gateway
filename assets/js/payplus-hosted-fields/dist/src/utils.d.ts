declare function deferred(): {
    resolve: (value: any) => void;
    reject: (error: any) => void;
    then: (cb: any) => void;
    catch: (cb: any) => void;
};
export { deferred };
