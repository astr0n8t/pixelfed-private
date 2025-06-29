{ pkgs ? import <nixpkgs> {} }:
pkgs.mkShell {
  name = "pixelfed-nix-shell";
  buildInputs = with pkgs; [ act docker php84 php84Packages.composer nodejs nodePackages.npm mkcert ddev hadolint gh ];
  runScript = "$SHELL";
  shellHook = ''
      export PATH="$PWD/node_modules/.bin/:$PATH"
      export PATH="$PWD/vendor/bin/:$PATH"
  '';
}
