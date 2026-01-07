export default class TreeHelper {
    constructor(flatData = []) {
        this.raw = flatData;
        this.tree = this.convertToTree(flatData);
    }

    /**
     * Chuyển đổi mảng phẳng sang cấu trúc cây
     */
    convertToTree(data) {
        const map = {};
        const tree = [];

        // Tạo bản sao để tránh thay đổi dữ liệu gốc
        data.forEach(item => {
            map[item.id] = {
                id: item.id,
                title: item.name || item.title,
                parentId: item.parent_id || 0,
                is_active: item.is_active,
                children: []
            };
        });

        data.forEach(item => {
            const currentNode = map[item.id];
            const parentId = item.parent_id;

            if (parentId === 0 || !parentId || !map[parentId]) {
                tree.push(currentNode);
            } else {
                map[parentId].children.push(currentNode);
            }
        });

        return this._removeEmptyChildren(tree);
    }

    /**
     * Đệ quy xóa thuộc tính children nếu mảng rỗng
     */
    _removeEmptyChildren(nodes) {
        return nodes.map(node => {
            const newNode = {...node};
            if (newNode.children.length === 0) {
                delete newNode.children;
            } else {
                newNode.children = this._removeEmptyChildren(newNode.children);
            }
            return newNode;
        });
    }

    /**
     * Tìm kiếm node theo ID
     */
    findId(id, nodes = this.tree) {
        for (const node of nodes) {
            if (node.id === id) return node;
            if (node.children) {
                const found = this.findId(id, node.children);
                if (found) return found;
            }
        }
        return null;
    }

    /**
     * Lọc cây dựa trên từ khóa (Giữ lại cấu trúc cha-con)
     */
    filter(searchTerm, nodes = this.tree) {
        if (!searchTerm) return this.tree;
        const search = searchTerm.toLowerCase();

        return nodes
            .map(node => ({...node}))
            .filter(node => {
                const hasTitleMatch = node.title.toLowerCase().includes(search);
                const childMatches = node.children ? this.filter(searchTerm, node.children) : [];

                if (childMatches.length > 0) {
                    node.children = childMatches;
                }

                return hasTitleMatch || childMatches.length > 0;
            });
    }
}
