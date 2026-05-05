{
  description = "ParkHub PHP - Laravel 13 plus React 19 toolchain";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { nixpkgs, flake-utils, ... }:
    flake-utils.lib.eachDefaultSystem (system:
      let
        pkgs = import nixpkgs { inherit system; };
        php = pkgs.php84;
        composer = pkgs.php84Packages.composer;
        nodejs = pkgs.nodejs_22;
      in
      {
        devShells.default = pkgs.mkShell {
          packages = with pkgs; [
            php
            composer
            nodejs
            git
            jq
            curl
            sqlite
            unzip
            zip
            pkg-config
            openssl
          ];

          env = {
            CI = "true";
            DB_CONNECTION = "sqlite";
            DB_DATABASE = "database/database.sqlite";
          };

          shellHook = ''
            echo "ParkHub PHP dev shell: PHP $(php -r 'echo PHP_VERSION;'), Node $(node --version)"
          '';
        };

        checks = {
          toolchain-contract = pkgs.runCommand "parkhub-php-toolchain-contract"
            {
              nativeBuildInputs = [
                php
                composer
                nodejs
                pkgs.jq
                pkgs.gnugrep
              ];
            }
            ''
              php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 ? 0 : 1);'
              composer --version >/dev/null
              node --version | grep -Eq '^v22\.'
              npm --version >/dev/null
              jq -e '.require.php == "^8.4"' ${./composer.json} >/dev/null
              jq -e '.engines.node == ">=22.12.0"' ${./parkhub-web/package.json} >/dev/null
              touch "$out"
            '';

          garnix-contract = pkgs.runCommand "parkhub-php-garnix-contract"
            {
              nativeBuildInputs = [ pkgs.gnugrep ];
            }
            ''
              test -f ${./garnix.yaml}
              grep -q 'checks.x86_64-linux.*' ${./garnix.yaml}
              grep -q 'devShells.x86_64-linux.default' ${./garnix.yaml}
              touch "$out"
            '';
        };

        formatter = pkgs.nixpkgs-fmt;
      });
}
