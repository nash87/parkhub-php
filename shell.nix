{ pkgs ? import <nixpkgs> {} }:
pkgs.mkShell {
  buildInputs = with pkgs; [
    php84
    php84Packages.composer
    nodejs_22
    git
    jq
    curl
    sqlite
    unzip
    zip
  ];
}
