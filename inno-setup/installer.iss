; Inno Setup Installer Script for Mikhmon Desktop by NODERA
; ══════════════════════════════════════════════════════════════

#define MyAppName "Mikhmon Desktop by NODERA"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "NODERA"
#define MyAppURL "https://dgtlnetsolution.com"
#define MyAppExeName "Mikhmon Desktop by NODERA.exe"
#define MyAppCopyright "Copyright 2026 NODERA"

[Setup]
AppId={{E58A4F10-B110-4A82-9D9E-B19F234D0911}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppVerName={#MyAppName} {#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={autopf}\Nodera Mikhmon Desktop
DisableProgramGroupPage=yes
LicenseFile=..\LICENSE.txt
OutputDir=..\dist
OutputBaseFilename=Mikhmon-Desktop-NODERA-Setup-{#MyAppVersion}
SetupIconFile=..\build\icon.ico
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
VersionInfoVersion={#MyAppVersion}
VersionInfoCompany={#MyAppPublisher}
VersionInfoDescription=Mikhmon Desktop by NODERA
VersionInfoCopyright={#MyAppCopyright}
VersionInfoProductName={#MyAppName}
VersionInfoProductVersion={#MyAppVersion}
PrivilegesRequired=lowest

[Languages]
Name: "indonesian"; MessagesFile: "compiler:Languages\Indonesian.isl"
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Files]
Source: "..\dist\win-unpacked\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{autoprograms}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(MyAppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent
