{ pkgs ? import <nixpkgs> {} }:
pkgs.mkShell {
  buildInputs = with pkgs; [
    php83
    php83Packages.composer
    nodejs_22
    nodePackages.npm
  ];
}
