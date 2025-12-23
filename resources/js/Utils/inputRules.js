export const rules = {
    required: [
        value => !!value || 'Vui lòng không bỏ trống'
    ],
    lowerCaseNumber: [
        value => {
            const pattern = /^[a-z][a-z0-9]*$/
            return pattern.test(value) || 'Phải bắt đầu bằng chữ, chỉ chứa chữ cái và số.'
        }
    ],
    strongPassword: [
        value => {
            const pattern = /^[a-zA-Z](?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{7,}$/
            return pattern.test(value) || 'Mật khẩu từ 8 ký tự, ít nhất 1 chữ in hoa, 1 số, 1 ký tự @$!%*?&.'
        }
    ]
}
