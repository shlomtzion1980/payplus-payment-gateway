import TerserPlugin = require("terser-webpack-plugin");
export const mode: string;
export const devtool: string;
export namespace entry {
    const main: string;
}
export namespace output {
    const path: string;
    const filename: string;
}
export namespace resolve {
    const extensions: string[];
}
export namespace module {
    const rules: {
        loader: string;
    }[];
}
export namespace optimization {
    const minimize: boolean;
    const minimizer: TerserPlugin<import("terser").MinifyOptions>[];
}
