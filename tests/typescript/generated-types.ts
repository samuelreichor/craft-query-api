/**
 * 
 *    ___                             _    ____ ___ 
 *   / _ \ _   _  ___ _ __ _   _     / \  |  _ \_ _|
 *  | | | | | | |/ _ \ '__| | | |   / _ \ | |_) | | 
 *  | |_| | |_| |  __/ |  | |_| |  / ___ \|  __/| | 
 *   \__\_\__,_|\___|_|   \__, | /_/   \_\_|  |___|
 *                         |___/                    
 *           
 * This file was generated by the Query API Type Generator.
 * Please do not edit this file manually. Changes made in this file might get lost.
 * To update it, run the 'craft query-api/typescript/generate-types' command.
 */

// --- hardTypes ---
export type CraftDateTime = {
    date: string
    timezone: string
    timezone_type: number
}

export type CraftColor = {
    hex: string
    rgb: string
    hsl: string
}

export type CraftCountry = {
    name: string
    countryCode: string
    threeLetterCode: string
    locale: string
    currencyCode: string
    timezones: string[]
}

export type CraftMoney = {
    amount: string
    currency: string
}

export type CraftLinkTarget = '_blank' | '_self'

export type CraftLink = {
    elementType: string
    url: string
    label: string
    target: CraftLinkTarget
    rel: string
    urlSuffix: string
    class: string
    id: string
    ariaLabel: string
    download: boolean
    downloadFile: string
}

export type CraftJson = object | object[]

export type CraftAssetFocalPoint = {
    x: number
    y: number
}

export type CraftAssetMeta = {
    id: number
    filename: string
    kind: string
    size: string
    mimeType: string
    extension: string
    cpEditUrl: string
    volumeId: number
}

export type CraftAddressMeta = {
    id: number
}

export type CraftUserStatus = 'inactive' | 'active' | 'pending' | 'credentialed' | 'suspended' | 'locked'

export type CraftUserMeta = {
    id: number
    status: CraftUserStatus
    cpEditUrl: string
}

export type CraftEntryRelation = {
    title: string
    slug: string
    url: string
    id: number
}

export type CraftEntryStatus = 'live' | 'pending' | 'expired' | 'disabled'

export type CraftEntryMeta = {
    id: number
    entryType: string
    sectionId: number
    siteId: number
    url: string
    slug: string
    uri: string
    fullUri: string
    status: CraftEntryStatus
    cpEditUrl: string
}

export interface CraftPageBase {
    metadata: CraftEntryMeta
    sectionHandle: string
    title: string
}

export type CraftTagMeta = {
    id: number
}

export type CraftTag = {
    metadata: CraftTagMeta
    title: string
    slug: string
}

// --- addresses ---
export type CraftAddress = {
    metadata: CraftAddressMeta
    title: string
    addressLine1: string
    addressLine2: string
    addressLine3: string
    countryCode: string
    locality: string
    postalCode: string
}

// --- assets ---
export type CraftAssetRatio = {
    auto: string
    '1:1': string
    '34': string
    '16 9': string
    '2/3': string
    dominantColor: string
}

export type CraftVolumeImages = {
    metadata: CraftAssetMeta
    height: number
    width: number
    focalPoint: CraftAssetFocalPoint
    url: string
    title: string
    srcSets: CraftAssetRatio
    altText: string | null
    linkField: CraftLink | null
}

export type CraftVolumeGraphics = {
    metadata: CraftAssetMeta
    height: number
    width: number
    focalPoint: CraftAssetFocalPoint
    url: string
    title: string
    srcSets: CraftAssetRatio
    alt: string | null
}

export type CraftAsset = CraftVolumeImages | CraftVolumeGraphics

// --- entryTypes ---
export interface CraftEntryTypeCta {
    title: string
    headlineTag: CraftOptionHeadlineTag
    plainText: string | null
    entries: (CraftEntryRelation)[] | null
}

export interface CraftEntryTypeImageText {
    asset: (CraftAsset)[] | null
    plainText: string | null
}

export interface CraftEntryTypeHyperLink {
    hyperField: DynamicHardType
}

export interface CraftEntryTypeLink {
    linkText: string | null
    openInNewTab: boolean | null
    linkField: CraftLink | null
}

export interface CraftEntryTypeNewsTeaser {
    categories: (CraftCategoryNewsFilter)[] | null
    newsTag: (CraftTag)[] | null
}

export interface CraftEntryTypeHome {
    title: string
    asset: (CraftAsset)[] | null
    selectAuthor: (CraftUser)[] | null
    plainText: string | null
    richtext: string | null
    contentBuilder: (CraftEntryTypeAuthor | CraftEntryTypeHeadline | CraftEntryTypeImageText | CraftEntryTypeNewsTeaser | CraftEntryTypeLink | CraftEntryTypeHyperLink)[] | null
    cta: (CraftEntryTypeCta)[] | null
}

export interface CraftEntryTypeHeadline {
    title: string
    headlineTag: CraftOptionHeadlineTag
}

export interface CraftEntryTypeAuthor {
    selectAuthor: (CraftUser)[] | null
    address: (CraftAddress)[] | null
    linkField: CraftLink | null
}

export interface CraftEntryTypeDefaultFields {
    title: string
    address: (CraftAddress)[]
    asset: (CraftAsset)[]
    buttonGroup: CraftOptionButtonGroup
    categories: (CraftCategoryNewsFilter)[]
    checkboxes: (CraftOptionCheckboxes)[]
    color: CraftColor
    contentBlock: CraftContentBlockContentBlock | null
    country: CraftCountry
    date: CraftDateTime | null
    dropdown: CraftOptionDropdown | null
    email: string | null
    entries: (CraftEntryRelation)[] | null
    iconField: string | null
    json: CraftJson | null
    lightswitch: boolean | null
    linkField: CraftLink | null
    matrix: (CraftEntryTypeHeadline | CraftEntryTypeImageText)[] | null
    money: CraftMoney | null
    multiSelect: (CraftOptionMultiSelect)[] | null
    number: number | null
    plainText: string | null
    radioButtons: CraftOptionRadioButtons | null
    range: number | null
    table: (CraftTableTable)[] | null
    tags: (CraftTag)[] | null
    time: CraftDateTime | null
    users: (CraftUser)[] | null
}

export interface CraftEntryTypeRelationalFieldsWithMaxSetting {
    title: string
    singleRelatedAddress: CraftAddress | null
    singleRelatedAsset: CraftAsset | null
    singleRelatedCategory: CraftCategoryNewsFilter | null
    singleMatrix: CraftEntryTypeCta | null
    singleRelatedEntry: CraftEntryRelation | null
    singleRelatedUser: CraftUser | null
    matrixMaxRelations: CraftEntryTypeRelationalFieldsWithMaxSetting | null
}

export interface CraftPageHome extends CraftEntryTypeHome {
    metadata: CraftEntryMeta
    title: string
    sectionHandle: string
}

export interface CraftPageDefaultFields extends CraftEntryTypeDefaultFields {
    metadata: CraftEntryMeta
    title: string
    sectionHandle: string
}

export interface CraftPageRelationalFieldsWithMaxSetting extends CraftEntryTypeRelationalFieldsWithMaxSetting {
    metadata: CraftEntryMeta
    title: string
    sectionHandle: string
}



// --- users ---
export type CraftUser = {
    metadata: CraftUserMeta
    username: string | null
    fullName: string | null
    photo: CraftAsset | null
    email: string | null
    address: (CraftAddress)[] | null
}

// --- categories ---
export interface CraftCategoryBlogFilters {
    title: string
    plainText: string | null
}

export interface CraftCategoryNewsFilter {
    title: string
    entries: (CraftEntryRelation)[] | null
    selectAuthor: (CraftUser)[] | null
}



// --- tables ---
export interface CraftTableTable {
    col1: string
    col1Handle: string
    col2: string
    col2Handle: string
}



// --- options ---
export type CraftOptionValueDropdown = 'optionA' | 'optionB'

export type CraftOptionDropdown = {
    label: string
    selected: boolean
    valid: boolean
    icon: string | null
    color: string | null
    value: CraftOptionValueDropdown
}

export type CraftOptionValueHeadlineTag = 'h1' | 'h2' | 'h3' | 'h4'

export type CraftOptionHeadlineTag = {
    label: string
    selected: boolean
    valid: boolean
    icon: string | null
    color: string | null
    value: CraftOptionValueHeadlineTag
}

export type CraftOptionValueRadioButtons = 'optionA' | 'optionB'

export type CraftOptionRadioButtons = {
    label: string
    selected: boolean
    valid: boolean
    icon: string | null
    color: string | null
    value: CraftOptionValueRadioButtons
}

export type CraftOptionValueCheckboxes = 'firstOption' | 'secondOption'

export type CraftOptionCheckboxes = {
    label: string
    selected: boolean
    valid: boolean
    icon: string | null
    color: string | null
    value: CraftOptionValueCheckboxes
}

export type CraftOptionValueMultiSelect = 'optionA' | 'optionB'

export type CraftOptionMultiSelect = {
    label: string
    selected: boolean
    valid: boolean
    icon: string | null
    color: string | null
    value: CraftOptionValueMultiSelect
}

export type CraftOptionValueButtonGroup = 'optionA' | 'optionB'

export type CraftOptionButtonGroup = {
    label: string
    selected: boolean
    valid: boolean
    icon: string | null
    color: string | null
    value: CraftOptionValueButtonGroup
}



// --- contentBlocks ---
export interface CraftContentBlockContentBlock {
    richtext: string | null
    singleMatrix: CraftEntryTypeCta | null
    matrix: (CraftEntryTypeHeadline | CraftEntryTypeImageText)[] | null
}



// --- customHardTypes ---
export type DynamicHardType = object[]

